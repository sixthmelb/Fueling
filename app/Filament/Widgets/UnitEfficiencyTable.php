<?php

namespace App\Filament\Widgets;

use App\Models\Unit;
use App\Models\UnitConsumptionSummary;
use App\Models\FuelTransaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UnitEfficiencyTable extends BaseWidget
{
    protected static ?string $heading = 'Unit Efficiency Performance';
    
    protected static ?int $sort = 3;
    
    protected static ?string $pollingInterval = '60s';
    
    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('unit_code')
                    ->label('Unit')
                    ->weight('bold')
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('unitType.type_name')
                    ->label('Type')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('today_transactions_count')
                    ->label('Today\'s Transactions')
                    ->state(function (Unit $record) {
                        return $record->fuelTransactions()
                            ->whereDate('transaction_datetime', today())
                            ->count();
                    })
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('today_fuel_consumed')
                    ->label('Today\'s Fuel')
                    ->state(function (Unit $record) {
                        return number_format(
                            $record->fuelTransactions()
                                ->whereDate('transaction_datetime', today())
                                ->sum('fuel_amount'), 
                            1
                        ) . ' L';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->withSum([
                            'fuelTransactions as today_fuel' => function ($query) {
                                $query->whereDate('transaction_datetime', today());
                            }
                        ], 'fuel_amount')
                        ->orderBy('today_fuel', $direction);
                    }),
                    
                Tables\Columns\TextColumn::make('avg_efficiency_per_hour')
                    ->label('Avg L/hour')
                    ->state(function (Unit $record) {
                        $avgEfficiency = $record->fuelTransactions()
                            ->whereDate('transaction_datetime', today())
                            ->whereNotNull('fuel_efficiency_per_hour')
                            ->avg('fuel_efficiency_per_hour');
                            
                        return $avgEfficiency ? number_format($avgEfficiency, 2) : '—';
                    })
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('avg_efficiency_per_km')
                    ->label('Avg L/km')
                    ->state(function (Unit $record) {
                        $avgEfficiency = $record->fuelTransactions()
                            ->whereDate('transaction_datetime', today())
                            ->whereNotNull('fuel_efficiency_per_km')
                            ->avg('fuel_efficiency_per_km');
                            
                        return $avgEfficiency ? number_format($avgEfficiency, 2) : '—';
                    })
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('efficiency_rating')
                    ->label('Rating')
                    ->state(function (Unit $record) {
                        $latestTransaction = $record->fuelTransactions()
                            ->whereDate('transaction_datetime', today())
                            ->latest('transaction_datetime')
                            ->first();
                            
                        return $latestTransaction ? $latestTransaction->getEfficiencyRating() : 'N/A';
                    })
                    ->badge()
                    ->color(function (Unit $record) {
                        $latestTransaction = $record->fuelTransactions()
                            ->whereDate('transaction_datetime', today())
                            ->latest('transaction_datetime')
                            ->first();
                            
                        if (!$latestTransaction) return 'gray';
                        
                        return match ($latestTransaction->getEfficiencyRating()) {
                            'Excellent' => 'success',
                            'Good' => 'primary',
                            'Average' => 'info',
                            'Below Average' => 'warning',
                            'Poor' => 'danger',
                            default => 'gray'
                        };
                    }),
                    
                Tables\Columns\TextColumn::make('last_fueling')
                    ->label('Last Fueling')
                    ->state(function (Unit $record) {
                        $lastTransaction = $record->fuelTransactions()
                            ->latest('transaction_datetime')
                            ->first();
                            
                        return $lastTransaction ? 
                            $lastTransaction->transaction_datetime->diffForHumans() : 
                            'No recent activity';
                    })
                    ->color('secondary'),
                    
                Tables\Columns\IconColumn::make('has_variance_issues')
                    ->label('Issues')
                    ->state(function (Unit $record) {
                        return $record->fuelTransactions()
                            ->whereDate('transaction_datetime', today())
                            ->where('has_variance', true)
                            ->exists();
                    })
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('unit_type_id')
                    ->label('Unit Type')
                    ->relationship('unitType', 'type_name')
                    ->multiple()
                    ->preload(),
                    
                Tables\Filters\Filter::make('has_activity_today')
                    ->label('Active Today')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('fuelTransactions', fn ($q) => 
                            $q->whereDate('transaction_datetime', today())
                        )
                    )
                    ->default(),
                    
                Tables\Filters\Filter::make('efficiency_issues')
                    ->label('Has Efficiency Issues')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereHas('fuelTransactions', fn ($q) => 
                            $q->whereDate('transaction_datetime', today())
                              ->where('has_variance', true)
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Unit $record) => 
                        url('/admin/units/' . $record->id)
                    ),
                    
                Tables\Actions\Action::make('view_transactions')
                    ->label('Transactions')
                    ->icon('heroicon-o-list-bullet')
                    ->color('primary')
                    ->url(fn (Unit $record) => 
                        url('/admin/fuel-transactions?unit=' . $record->unit_code)
                    ),
            ])
            ->defaultSort('today_fuel_consumed', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }
    
    protected function getTableQuery(): Builder
    {
        return Unit::query()
            ->where('is_active', true)
            ->whereHas('fuelTransactions', function ($query) {
                $query->whereDate('transaction_datetime', today());
            })
            ->with(['unitType', 'fuelTransactions' => function ($query) {
                $query->whereDate('transaction_datetime', today())
                      ->latest('transaction_datetime');
            }])
            ->withCount([
                'fuelTransactions as today_transactions_count' => function ($query) {
                    $query->whereDate('transaction_datetime', today());
                }
            ])
            ->withSum([
                'fuelTransactions as today_fuel_consumed' => function ($query) {
                    $query->whereDate('transaction_datetime', today());
                }
            ], 'fuel_amount');
    }
    
    protected function getTableDescription(): ?string
    {
        $totalUnits = Unit::where('is_active', true)->count();
        $activeToday = Unit::whereHas('fuelTransactions', fn ($q) => 
            $q->whereDate('transaction_datetime', today())
        )->count();
        
        $totalFuelToday = FuelTransaction::whereDate('transaction_datetime', today())
            ->sum('fuel_amount');
            
        return "Showing {$activeToday} of {$totalUnits} active units | Total consumption today: " . 
               number_format($totalFuelToday, 0) . "L";
    }
    
    public static function canView(): bool
    {
        return true;
    }
    
    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No unit activity today';
    }
    
    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Units will appear here once they have fuel transactions for today.';
    }
    
    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-truck';
    }
}