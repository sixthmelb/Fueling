<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitTypeResource\Pages;
use App\Models\UnitType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class UnitTypeResource extends Resource
{
    protected static ?string $model = UnitType::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-plus';

    protected static ?string $navigationLabel = 'Unit Types';
    
    protected static ?string $navigationGroup = 'Master Data';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('type_code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(10)
                                    ->placeholder('e.g., DT, EX, DZ')
                                    ->helperText('Unique code for unit type'),
                                    
                                Forms\Components\TextInput::make('type_name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g., Dump Truck, Excavator')
                                    ->helperText('Descriptive name for unit type'),
                            ]),
                            
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Optional description of unit type'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Active unit types can be assigned to units'),
                    ]),
                    
                Forms\Components\Section::make('Default Consumption Rates')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('default_consumption_per_hour')
                                    ->numeric()
                                    ->step(0.01)
                                    ->suffix('L/hour')
                                    ->placeholder('0.00')
                                    ->helperText('Default fuel consumption per hour'),
                                    
                                Forms\Components\TextInput::make('default_consumption_per_km')
                                    ->numeric()
                                    ->step(0.01)
                                    ->suffix('L/km')
                                    ->placeholder('0.00')
                                    ->helperText('Default fuel consumption per kilometer'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('type_name')
                    ->label('Type Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('units_count')
                    ->label('Total Units')
                    ->counts('units')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('active_units_count')
                    ->label('Active Units')
                    ->counts('activeUnits')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('default_consumption_per_hour')
                    ->label('Default L/hour')
                    ->numeric(2)
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('default_consumption_per_km')
                    ->label('Default L/km')
                    ->numeric(2)
                    ->placeholder('—'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All unit types')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this unit type? This action cannot be undone and may affect related units.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('type_code');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Unit Type Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('type_code')
                                    ->label('Type Code')
                                    ->weight('bold')
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('type_name')
                                    ->label('Type Name'),
                                    
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Active Status')
                                    ->boolean(),
                                    
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Default Consumption Rates')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('default_consumption_per_hour')
                                    ->label('Per Hour')
                                    ->suffix(' L/hour')
                                    ->placeholder('Not set'),
                                    
                                Infolists\Components\TextEntry::make('default_consumption_per_km')
                                    ->label('Per Kilometer')
                                    ->suffix(' L/km')
                                    ->placeholder('Not set'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('units_count')
                                    ->label('Total Units')
                                    ->state(fn($record) => $record->getTotalUnitsCount())
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('active_units_count')
                                    ->label('Active Units')
                                    ->state(fn($record) => $record->getActiveUnitsCount())
                                    ->badge()
                                    ->color('success'),
                                    
                                Infolists\Components\TextEntry::make('consumption_rates_count')
                                    ->label('Consumption Rates')
                                    ->state(fn($record) => $record->fuelConsumptionRates()->count())
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
            'index' => Pages\ListUnitTypes::route('/'),
            'create' => Pages\CreateUnitType::route('/create'),
            //'view' => Pages\ViewUnitType::route('/{record}'),
            'edit' => Pages\EditUnitType::route('/{record}/edit'),
        ];
    }
}