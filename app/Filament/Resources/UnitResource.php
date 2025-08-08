<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use App\Models\UnitType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Units';
    
    protected static ?string $navigationGroup = 'Master Data';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('unit_code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g., DT-001, EX-002')
                                    ->helperText('Unique identifier for the unit'),
                                    
                                Forms\Components\TextInput::make('unit_name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g., Dump Truck #1')
                                    ->helperText('Descriptive name for the unit'),
                            ]),
                            
                        Forms\Components\Select::make('unit_type_id')
                            ->label('Unit Type')
                            ->relationship('unitType', 'type_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('type_code')
                                    ->required()
                                    ->maxLength(10),
                                Forms\Components\TextInput::make('type_name')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->helperText('Select or create a unit type'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Active units can receive fuel transactions'),
                    ]),
                    
                Forms\Components\Section::make('Current Meters')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('current_hour_meter')
                                    ->label('Hour Meter')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->suffix('hours')
                                    ->default(0)
                                    ->helperText('Current hour meter reading'),
                                    
                                Forms\Components\TextInput::make('current_odometer')
                                    ->label('Odometer')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->suffix('km')
                                    ->default(0)
                                    ->helperText('Current odometer reading'),
                            ]),
                    ]),
                    
                Forms\Components\Section::make('Specifications')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('brand')
                                    ->maxLength(50)
                                    ->placeholder('e.g., Caterpillar, Komatsu'),
                                    
                                Forms\Components\TextInput::make('model')
                                    ->maxLength(50)
                                    ->placeholder('e.g., 797F, PC200'),
                                    
                                Forms\Components\TextInput::make('manufacture_year')
                                    ->label('Year')
                                    ->numeric()
                                    ->minValue(1990)
                                    ->maxValue(date('Y'))
                                    ->placeholder(date('Y')),
                            ]),
                            
                        Forms\Components\TextInput::make('fuel_tank_capacity')
                            ->label('Fuel Tank Capacity')
                            ->numeric()
                            ->step(0.01)
                            ->suffix('L')
                            ->helperText('Maximum fuel tank capacity in liters'),
                            
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Additional notes about this unit'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unit_code')
                    ->label('Unit Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('unit_name')
                    ->label('Unit Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('unitType.type_name')
                    ->label('Type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('current_hour_meter')
                    ->label('Hour Meter')
                    ->suffix(' hrs')
                    ->numeric(2)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('current_odometer')
                    ->label('Odometer')
                    ->suffix(' km')
                    ->numeric(2)
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('full_spec')
                    ->label('Specifications')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->full_spec),
                    
                Tables\Columns\TextColumn::make('todayTransactionsCount')
                    ->label("Today's Fueling")
                    ->state(fn($record) => $record->todayTransactionsCount())
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray'),
                    
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
                Tables\Filters\SelectFilter::make('unit_type_id')
                    ->label('Unit Type')
                    ->relationship('unitType', 'type_name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All units')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                    
                Tables\Filters\Filter::make('has_recent_activity')
                    ->label('Recent Activity')
                    ->query(fn($query) => $query->whereHas('fuelTransactions', fn($q) => 
                        $q->where('transaction_datetime', '>=', now()->subDays(7))
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('quick_fuel')
                    ->label('Quick Fuel')
                    ->icon('heroicon-o-bolt')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('fuel_amount')
                            ->label('Fuel Amount')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->suffix('L'),
                        Forms\Components\TextInput::make('hour_meter')
                            ->label('Current Hour Meter')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->default(fn($record) => $record->current_hour_meter),
                        Forms\Components\TextInput::make('odometer')
                            ->label('Current Odometer')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->default(fn($record) => $record->current_odometer),
                    ])
                    ->action(function (array $data, Unit $record) {
                        // This would create a quick fuel transaction
                        // Implementation would need FuelTransaction creation logic
                    })
                    ->visible(fn($record) => $record->is_active),
                    
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this unit? This action cannot be undone.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn($records) => $records->each->update(['is_active' => true])),
                        
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn($records) => $records->each->update(['is_active' => false])),
                        
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('unit_code');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Unit Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('unit_code')
                                    ->label('Unit Code')
                                    ->weight('bold')
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('unit_name')
                                    ->label('Unit Name'),
                                    
                                Infolists\Components\TextEntry::make('unitType.type_name')
                                    ->label('Unit Type')
                                    ->badge(),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('current_hour_meter')
                                    ->label('Hour Meter')
                                    ->suffix(' hours'),
                                    
                                Infolists\Components\TextEntry::make('current_odometer')
                                    ->label('Odometer')
                                    ->suffix(' km'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Specifications')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('brand')
                                    ->placeholder('Not specified'),
                                    
                                Infolists\Components\TextEntry::make('model')
                                    ->placeholder('Not specified'),
                                    
                                Infolists\Components\TextEntry::make('manufacture_year')
                                    ->label('Year')
                                    ->placeholder('Not specified'),
                                    
                                Infolists\Components\TextEntry::make('age')
                                    ->label('Age')
                                    ->suffix(' years')
                                    ->placeholder('Unknown'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('fuel_tank_capacity')
                            ->label('Fuel Tank Capacity')
                            ->suffix(' L')
                            ->placeholder('Not specified'),
                            
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No additional notes')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Activity Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('todayTransactionsCount')
                                    ->label("Today's Fueling")
                                    ->state(fn($record) => $record->todayTransactionsCount())
                                    ->badge()
                                    ->color('success'),
                                    
                                Infolists\Components\TextEntry::make('todayTotalFuelConsumption')
                                    ->label("Today's Fuel")
                                    ->state(fn($record) => number_format($record->todayTotalFuelConsumption(), 2) . ' L')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('averageConsumptionPerHour')
                                    ->label('Avg L/hour')
                                    ->state(fn($record) => $record->getAverageConsumptionPerHour() ? 
                                        number_format($record->getAverageConsumptionPerHour(), 4) : 'No data')
                                    ->badge()
                                    ->color('warning'),
                                    
                                Infolists\Components\TextEntry::make('averageConsumptionPerKm')
                                    ->label('Avg L/km')
                                    ->state(fn($record) => $record->getAverageConsumptionPerKm() ? 
                                        number_format($record->getAverageConsumptionPerKm(), 4) : 'No data')
                                    ->badge()
                                    ->color('warning'),
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
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'view' => Pages\ViewUnit::route('/{record}'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}