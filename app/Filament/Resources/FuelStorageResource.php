<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelStorageResource\Pages;
use App\Models\FuelStorage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class FuelStorageResource extends Resource
{
    protected static ?string $model = FuelStorage::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Fuel Storage';
    
    protected static ?string $navigationGroup = 'Master Data';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Storage Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('storage_code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g., ST-001, MAIN-TANK')
                                    ->helperText('Unique identifier for storage tank'),
                                    
                                Forms\Components\TextInput::make('storage_name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g., Main Storage Tank #1')
                                    ->helperText('Descriptive name for storage'),
                            ]),
                            
                        Forms\Components\TextInput::make('location')
                            ->maxLength(255)
                            ->placeholder('e.g., Workshop Area, North Site')
                            ->helperText('Physical location of storage tank'),
                            
                        Forms\Components\Select::make('fuel_type')
                            ->options([
                                'Solar' => 'Solar (Diesel)',
                                'Bensin' => 'Bensin (Gasoline)',
                                'Pertamax' => 'Pertamax (Premium)',
                            ])
                            ->default('Solar')
                            ->required()
                            ->helperText('Type of fuel stored'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Active storage can receive and distribute fuel'),
                    ]),
                    
                Forms\Components\Section::make('Capacity & Levels')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('capacity')
                                    ->label('Total Capacity')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.01)
                                    ->suffix('L')
                                    ->helperText('Maximum storage capacity'),
                                    
                                Forms\Components\TextInput::make('current_level')
                                    ->label('Current Level')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->suffix('L')
                                    ->default(0)
                                    ->helperText('Current fuel level'),
                                    
                                Forms\Components\TextInput::make('minimum_level')
                                    ->label('Minimum Level')
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->suffix('L')
                                    ->default(0)
                                    ->helperText('Alert threshold for low fuel'),
                            ]),
                            
                        Forms\Components\Placeholder::make('usage_info')
                            ->label('Usage Information')
                            ->content(function ($get) {
                                $capacity = $get('capacity') ?: 0;
                                $current = $get('current_level') ?: 0;
                                
                                if ($capacity > 0) {
                                    $percentage = round(($current / $capacity) * 100, 2);
                                    $remaining = $capacity - $current;
                                    
                                    return "Usage: {$percentage}% ({$current}L / {$capacity}L) | Remaining: {$remaining}L";
                                }
                                
                                return 'Enter capacity and current level to see usage info';
                            })
                            ->helperText('Real-time capacity usage calculation'),
                    ]),
                    
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Additional description or notes about this storage'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->live(); // Enable live updates for calculations
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('storage_code')
                    ->label('Storage Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('storage_name')
                    ->label('Storage Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('fuel_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Solar' => 'success',
                        'Bensin' => 'warning', 
                        'Pertamax' => 'info',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('current_level')
                    ->label('Current Level')
                    ->suffix(' L')
                    ->numeric(2)
                    ->sortable()
                    ->color(fn ($record) => $record->isLowLevel() ? 'danger' : 'success'),
                    
                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->suffix(' L')
                    ->numeric(2)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('capacity_usage_percentage')
                    ->label('Usage')
                    ->state(fn ($record) => $record->getCapacityUsagePercentage() . '%')
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->isLowLevel() => 'danger',
                        $record->getCapacityUsagePercentage() >= 80 => 'success',
                        $record->getCapacityUsagePercentage() >= 50 => 'warning',
                        default => 'danger'
                    }),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),
                    
                Tables\Columns\TextColumn::make('location')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->location),
                    
                Tables\Columns\TextColumn::make('todayTotalTransferred')
                    ->label("Today's Transfers")
                    ->state(fn ($record) => number_format($record->todayTotalTransferred(), 2) . ' L')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('fuel_type')
                    ->options([
                        'Solar' => 'Solar (Diesel)',
                        'Bensin' => 'Bensin (Gasoline)', 
                        'Pertamax' => 'Pertamax (Premium)',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All storage')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                    
                Tables\Filters\Filter::make('low_level')
                    ->label('Low Level Alert')
                    ->query(fn ($query) => $query->where('current_level', '<=', 'minimum_level')),
                    
                Tables\Filters\Filter::make('high_capacity')
                    ->label('High Usage (>80%)')
                    ->query(fn ($query) => $query->whereRaw('(current_level / capacity) * 100 > 80')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('adjust_level')
                    ->label('Adjust Level')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_level')
                            ->label('New Fuel Level')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->suffix('L')
                            ->helperText('Enter the actual measured fuel level'),
                        Forms\Components\Textarea::make('adjustment_reason')
                            ->label('Reason for Adjustment')
                            ->required()
                            ->placeholder('e.g., Physical measurement correction, inventory adjustment'),
                    ])
                    ->action(function (array $data, FuelStorage $record) {
                        $oldLevel = $record->current_level;
                        $record->updateLevel($data['new_level']);
                        
                        // Log the adjustment
                        activity('fuel_storage_adjustment')
                            ->performedOn($record)
                            ->withProperties([
                                'old_level' => $oldLevel,
                                'new_level' => $data['new_level'],
                                'difference' => $data['new_level'] - $oldLevel,
                                'reason' => $data['adjustment_reason']
                            ])
                            ->log('Storage level manually adjusted');
                    })
                    ->visible(fn ($record) => $record->is_active),
                    
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this storage? This action cannot be undone.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                        
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('storage_code');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Storage Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('storage_code')
                                    ->label('Storage Code')
                                    ->weight('bold')
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('storage_name')
                                    ->label('Storage Name'),
                                    
                                Infolists\Components\TextEntry::make('fuel_type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Solar' => 'success',
                                        'Bensin' => 'warning',
                                        'Pertamax' => 'info',
                                        default => 'gray',
                                    }),
                            ]),
                            
                        Infolists\Components\TextEntry::make('location')
                            ->placeholder('Location not specified')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Capacity & Status')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('capacity')
                                    ->label('Total Capacity')
                                    ->suffix(' L')
                                    ->weight('bold'),
                                    
                                Infolists\Components\TextEntry::make('current_level')
                                    ->label('Current Level')
                                    ->suffix(' L')
                                    ->color(fn ($record) => $record->isLowLevel() ? 'danger' : 'success'),
                                    
                                Infolists\Components\TextEntry::make('minimum_level')
                                    ->label('Minimum Level')
                                    ->suffix(' L'),
                                    
                                Infolists\Components\TextEntry::make('remaining_capacity')
                                    ->label('Remaining Capacity')
                                    ->state(fn ($record) => number_format($record->getRemainingCapacity(), 2))
                                    ->suffix(' L'),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('capacity_usage_percentage')
                                    ->label('Usage Percentage')
                                    ->state(fn ($record) => $record->getCapacityUsagePercentage() . '%')
                                    ->badge()
                                    ->color(fn ($record) => match (true) {
                                        $record->isLowLevel() => 'danger',
                                        $record->getCapacityUsagePercentage() >= 80 => 'success',
                                        $record->getCapacityUsagePercentage() >= 50 => 'warning',
                                        default => 'danger'
                                    }),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($record) => $record->status_color),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Activity Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('todayTotalTransferred')
                                    ->label("Today's Transfers Out")
                                    ->state(fn ($record) => number_format($record->todayTotalTransferred(), 2) . ' L')
                                    ->badge()
                                    ->color('warning'),
                                    
                                Infolists\Components\TextEntry::make('todayTotalDirectFuel')
                                    ->label("Today's Direct Fueling")
                                    ->state(fn ($record) => number_format($record->todayTotalDirectFuel(), 2) . ' L')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('latest_stock_check')
                                    ->label('Latest Stock Check')
                                    ->state(fn ($record) => $record->getLatestStockCheck()?->formatted_date_time ?? 'No checks')
                                    ->badge()
                                    ->color('secondary'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Additional Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Active Status')
                                    ->boolean(),
                                    
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFuelStorages::route('/'),
            'create' => Pages\CreateFuelStorage::route('/create'),
            //'view' => Pages\ViewFuelStorage::route('/{record}'),
            'edit' => Pages\EditFuelStorage::route('/{record}/edit'),
        ];
    }
}