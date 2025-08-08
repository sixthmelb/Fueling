<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelTransactionResource\Pages;
use App\Models\FuelTransaction;
use App\Models\Unit;
use App\Models\DailySession;
use App\Models\FuelStorage;
use App\Models\FuelTruck;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class FuelTransactionResource extends Resource
{
    protected static ?string $model = FuelTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Fuel Transactions';
    
    protected static ?string $navigationGroup = 'Operations';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('transaction_number')
                                    ->maxLength(50)
                                    ->placeholder('Auto-generated if empty')
                                    ->helperText('Unique transaction number'),
                                    
                                Forms\Components\DateTimePicker::make('transaction_datetime')
                                    ->label('Transaction Date & Time')
                                    ->required()
                                    ->default(now())
                                    ->seconds(false)
                                    ->helperText('When this fueling occurred'),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('unit_id')
                                    ->label('Unit')
                                    ->relationship('unit', 'unit_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        if ($state) {
                                            $unit = Unit::find($state);
                                            if ($unit) {
                                                // Get the latest transaction for this unit to get the last readings
                                                $latestTransaction = $unit->fuelTransactions()
                                                    ->latest('transaction_datetime')
                                                    ->first();
                                                
                                                if ($latestTransaction) {
                                                    // Use the current readings from the latest transaction as previous readings
                                                    $set('previous_hour_meter', $latestTransaction->current_hour_meter);
                                                    $set('current_hour_meter', $latestTransaction->current_hour_meter);
                                                    $set('previous_odometer', $latestTransaction->current_odometer);
                                                    $set('current_odometer', $latestTransaction->current_odometer);
                                                } else {
                                                    // If no previous transactions, use unit's base readings
                                                    $set('previous_hour_meter', $unit->current_hour_meter);
                                                    $set('current_hour_meter', $unit->current_hour_meter);
                                                    $set('previous_odometer', $unit->current_odometer);
                                                    $set('current_odometer', $unit->current_odometer);
                                                }
                                            }
                                        }
                                    })
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                                    ->helperText('Select unit receiving fuel'),
                                    
                                Forms\Components\Select::make('daily_session_id')
                                    ->label('Daily Session')
                                    ->relationship('dailySession', 'session_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                                    ->helperText('Associated work session'),
                            ]),
                            
                        Forms\Components\TextInput::make('operator_name')
                            ->label('Operator Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Name of person performing fueling')
                            ->helperText('Person responsible for this transaction'),
                    ]),
                    
                Forms\Components\Section::make('Fuel Source')
                    ->description('Select either Storage (direct) or Truck (mobile) fueling')
                    ->schema([
                        Forms\Components\Radio::make('fuel_source_type')
                            ->label('Fuel Source Type')
                            ->options([
                                'App\Models\FuelStorage' => 'Direct from Storage',
                                'App\Models\FuelTruck' => 'Mobile Truck',
                            ])
                            ->descriptions([
                                'App\Models\FuelStorage' => 'Unit fueled directly from storage tank',
                                'App\Models\FuelTruck' => 'Unit fueled by mobile fuel truck',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (callable $set) {
                                $set('fuel_source_id', null);
                                $set('source_level_before', null);
                            })
                            ->columnSpanFull(),
                            
                        Forms\Components\Select::make('fuel_source_id')
                            ->label('Fuel Source')
                            ->required()
                            ->reactive()
                            ->options(function (callable $get) {
                                $sourceType = $get('fuel_source_type');
                                
                                if ($sourceType === 'App\Models\FuelStorage') {
                                    return FuelStorage::where('is_active', true)
                                        ->pluck('storage_name', 'id');
                                } elseif ($sourceType === 'App\Models\FuelTruck') {
                                    return FuelTruck::where('is_active', true)
                                        ->pluck('truck_name', 'id');
                                }
                                
                                return [];
                            })
                            ->afterStateUpdated(function (callable $set, $get, $state) {
                                $sourceType = $get('fuel_source_type');
                                
                                if ($state && $sourceType) {
                                    if ($sourceType === 'App\Models\FuelStorage') {
                                        $source = FuelStorage::find($state);
                                    } else {
                                        $source = FuelTruck::find($state);
                                    }
                                    
                                    if ($source) {
                                        $set('source_level_before', $source->current_level);
                                    }
                                }
                            })
                            ->helperText('Select fuel source based on type above'),
                            
                        Forms\Components\TextInput::make('source_level_before')
                            ->label('Source Level Before')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('L')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Current fuel level in source'),
                    ])
                    ->live(),
                    
                Forms\Components\Section::make('Unit Meters & Fuel')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Fieldset::make('Hour Meter Readings')
                                    ->schema([
                                        Forms\Components\TextInput::make('previous_hour_meter')
                                            ->label('Previous Reading')
                                            ->required()
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('hours')
                                            ->disabled()
                                            ->dehydrated()
                                            ->helperText('Last recorded hour meter'),
                                            
                                        Forms\Components\TextInput::make('current_hour_meter')
                                            ->label('Current Reading')
                                            ->required()
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('hours')
                                            ->gte('previous_hour_meter')
                                            ->helperText('Current hour meter reading'),
                                    ]),
                                    
                                Forms\Components\Fieldset::make('Odometer Readings')
                                    ->schema([
                                        Forms\Components\TextInput::make('previous_odometer')
                                            ->label('Previous Reading')
                                            ->required()
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('km')
                                            ->disabled()
                                            ->dehydrated()
                                            ->helperText('Last recorded odometer'),
                                            
                                        Forms\Components\TextInput::make('current_odometer')
                                            ->label('Current Reading')
                                            ->required()
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('km')
                                            ->gte('previous_odometer')
                                            ->helperText('Current odometer reading'),
                                    ]),
                            ]),
                            
                        Forms\Components\TextInput::make('fuel_amount')
                            ->label('Fuel Amount')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->suffix('L')
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $get, $state) {
                                $sourceLevel = $get('source_level_before') ?: 0;
                                $fuelAmount = $state ?: 0;
                                
                                $set('source_level_after', max(0, $sourceLevel - $fuelAmount));
                            })
                            ->helperText('Amount of fuel dispensed in liters'),
                            
                        Forms\Components\TextInput::make('source_level_after')
                            ->label('Source Level After')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('L')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Calculated fuel level after transaction'),
                    ])
                    ->live(),
                    
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Additional notes about this transaction'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_number')
                    ->label('Transaction #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->placeholder('Auto-generated'),
                    
                Tables\Columns\TextColumn::make('transaction_datetime')
                    ->label('Date & Time')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('unit.unit_code')
                    ->label('Unit')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('fuel_source_name')
                    ->label('Fuel Source')
                    ->badge()
                    ->color(fn ($record) => match ($record->fuel_source_type) {
                        'App\Models\FuelStorage' => 'success',
                        'App\Models\FuelTruck' => 'warning',
                        default => 'gray'
                    }),
                    
                Tables\Columns\TextColumn::make('fuel_amount')
                    ->label('Fuel Amount')
                    ->suffix(' L')
                    ->numeric(2)
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('hour_meter_diff')
                    ->label('HM Diff')
                    ->state(fn ($record) => number_format($record->getHourMeterDiff(), 2) . ' hrs')
                    ->color('secondary'),
                    
                Tables\Columns\TextColumn::make('odometer_diff')
                    ->label('Odo Diff')
                    ->state(fn ($record) => number_format($record->getOdometerDiff(), 2) . ' km')
                    ->color('secondary'),
                    
                Tables\Columns\TextColumn::make('fuel_efficiency_per_hour')
                    ->label('L/hr')
                    ->numeric(4)
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('fuel_efficiency_per_km')
                    ->label('L/km')
                    ->numeric(4)
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('efficiency_rating')
                    ->label('Rating')
                    ->state(fn ($record) => $record->getEfficiencyRating())
                    ->badge()
                    ->color(fn ($record) => $record->efficiency_color),
                    
                Tables\Columns\TextColumn::make('operator_name')
                    ->label('Operator')
                    ->searchable()
                    ->limit(15),
                    
                Tables\Columns\IconColumn::make('has_variance')
                    ->label('Variance')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')
                    ->falseColor('success'),
            ])
            ->filters([
                Tables\Filters\Filter::make('transaction_datetime')
                    ->form([
                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Transaction Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['transaction_date'], fn ($q) => $q->whereDate('transaction_datetime', $data['transaction_date']));
                    }),
                    
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->relationship('unit', 'unit_code')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('fuel_source_type')
                    ->label('Source Type')
                    ->options([
                        'App\Models\FuelStorage' => 'Storage',
                        'App\Models\FuelTruck' => 'Truck',
                    ]),
                    
                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Transactions')
                    ->query(fn ($query) => $query->whereDate('transaction_datetime', today())),
                    
                Tables\Filters\Filter::make('large_amounts')
                    ->label('Large Amounts (>500L)')
                    ->query(fn ($query) => $query->where('fuel_amount', '>', 500)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate')
                    ->icon('heroicon-o-calculator')
                    ->color('info')
                    ->action(function (FuelTransaction $record) {
                        $record->calculateEfficiency();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Efficiency recalculated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('recalculate_efficiency')
                        ->label('Recalculate Efficiency')
                        ->icon('heroicon-o-calculator')
                        ->color('info')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $record->calculateEfficiency();
                                $count++;
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title("Recalculated {$count} transaction(s)")
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('transaction_datetime', 'desc');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transaction Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('transaction_number')
                                    ->label('Transaction Number')
                                    ->weight('bold')
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('transaction_datetime')
                                    ->label('Date & Time')
                                    ->dateTime('d/m/Y H:i'),
                                    
                                Infolists\Components\TextEntry::make('operator_name')
                                    ->label('Operator')
                                    ->weight('bold'),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('unit.display_name')
                                    ->label('Unit')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('dailySession.display_name')
                                    ->label('Daily Session')
                                    ->badge()
                                    ->color('secondary'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Fuel Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('fuel_source_name')
                                    ->label('Fuel Source')
                                    ->weight('bold'),
                                    
                                Infolists\Components\TextEntry::make('fuel_amount')
                                    ->label('Fuel Amount')
                                    ->suffix(' L')
                                    ->weight('bold')
                                    ->color('success'),
                                    
                                Infolists\Components\TextEntry::make('efficiency_rating')
                                    ->label('Efficiency Rating')
                                    ->state(fn ($record) => $record->getEfficiencyRating())
                                    ->badge()
                                    ->color(fn ($record) => $record->efficiency_color),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Meter Readings')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('previous_hour_meter')
                                        ->label('Previous Hour Meter')
                                        ->suffix(' hours'),
                                    Infolists\Components\TextEntry::make('current_hour_meter')
                                        ->label('Current Hour Meter')
                                        ->suffix(' hours'),
                                    Infolists\Components\TextEntry::make('hour_meter_diff')
                                        ->label('Difference')
                                        ->state(fn ($record) => number_format($record->getHourMeterDiff(), 2) . ' hours')
                                        ->color('info')
                                        ->weight('bold'),
                                ]),
                                
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('previous_odometer')
                                        ->label('Previous Odometer')
                                        ->suffix(' km'),
                                    Infolists\Components\TextEntry::make('current_odometer')
                                        ->label('Current Odometer')
                                        ->suffix(' km'),
                                    Infolists\Components\TextEntry::make('odometer_diff')
                                        ->label('Difference')
                                        ->state(fn ($record) => number_format($record->getOdometerDiff(), 2) . ' km')
                                        ->color('info')
                                        ->weight('bold'),
                                ]),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Efficiency Analysis')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('fuel_efficiency_per_hour')
                                    ->label('Efficiency per Hour')
                                    ->suffix(' L/hr')
                                    ->numeric(4)
                                    ->placeholder('Not calculated'),
                                    
                                Infolists\Components\TextEntry::make('fuel_efficiency_per_km')
                                    ->label('Efficiency per KM')
                                    ->suffix(' L/km')
                                    ->numeric(4)
                                    ->placeholder('Not calculated'),
                                    
                                Infolists\Components\TextEntry::make('combined_efficiency')
                                    ->label('Combined Efficiency')
                                    ->suffix(' L/combined')
                                    ->numeric(4)
                                    ->placeholder('Not calculated'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No additional notes')
                            ->columnSpanFull(),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d/m/Y H:i'),
                                    
                                Infolists\Components\TextEntry::make('calculated_at')
                                    ->label('Calculated At')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('Not calculated yet'),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFuelTransactions::route('/'),
            'create' => Pages\CreateFuelTransaction::route('/create'),
            'view' => Pages\ViewFuelTransaction::route('/{record}'),
            'edit' => Pages\EditFuelTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('transaction_datetime', today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $todayCount = static::getModel()::whereDate('transaction_datetime', today())->count();
        
        return match (true) {
            $todayCount > 50 => 'success',
            $todayCount > 20 => 'warning', 
            $todayCount > 0 => 'primary',
            default => 'gray'
        };
    }
}