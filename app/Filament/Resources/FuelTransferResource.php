<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelTransferResource\Pages;
use App\Models\FuelTransfer;
use App\Models\FuelStorage;
use App\Models\FuelTruck;
use App\Models\DailySession;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class FuelTransferResource extends Resource
{
    protected static ?string $model = FuelTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-circle';

    protected static ?string $navigationLabel = 'Fuel Transfers';
    
    protected static ?string $navigationGroup = 'Operations';
    
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transfer Information')
                    ->schema([
                        Forms\Components\TextInput::make('transfer_number')
                            ->maxLength(50)
                            ->placeholder('Auto-generated if empty')
                            ->helperText('Unique transfer number'),
                            
                        Forms\Components\DateTimePicker::make('transfer_datetime')
                            ->label('Transfer Date & Time')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->helperText('When this transfer occurred'),
                            
                        Forms\Components\TextInput::make('operator_name')
                            ->label('Operator Name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Name of person performing transfer')
                            ->helperText('Person responsible for this transfer'),
                    ]),
                    
                Forms\Components\Section::make('Transfer Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('fuel_storage_id')
                                    ->label('From Storage')
                                    ->relationship('fuelStorage', 'storage_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        if ($state) {
                                            $storage = FuelStorage::find($state);
                                            if ($storage) {
                                                $set('storage_level_before', $storage->current_level);
                                            }
                                        }
                                    })
                                    ->helperText('Source storage tank'),
                                    
                                Forms\Components\Select::make('fuel_truck_id')
                                    ->label('To Truck')
                                    ->relationship('fuelTruck', 'truck_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        if ($state) {
                                            $truck = FuelTruck::find($state);
                                            if ($truck) {
                                                $set('truck_level_before', $truck->current_level);
                                            }
                                        }
                                    })
                                    ->helperText('Destination fuel truck'),
                            ]),
                            
                        Forms\Components\Select::make('daily_session_id')
                            ->label('Daily Session')
                            ->relationship('dailySession', 'session_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name)
                            ->helperText('Associated work session'),
                            
                        Forms\Components\TextInput::make('transferred_amount')
                            ->label('Transfer Amount')
                            ->required()
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->suffix('L')
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $get, $state) {
                                $storageLevel = $get('storage_level_before') ?: 0;
                                $truckLevel = $get('truck_level_before') ?: 0;
                                $transferAmount = $state ?: 0;
                                
                                $set('storage_level_after', max(0, $storageLevel - $transferAmount));
                                $set('truck_level_after', $truckLevel + $transferAmount);
                            })
                            ->helperText('Amount of fuel to transfer in liters'),
                    ])
                    ->live(),
                    
                Forms\Components\Section::make('Level Tracking')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Fieldset::make('Storage Levels')
                                    ->schema([
                                        Forms\Components\TextInput::make('storage_level_before')
                                            ->label('Before Transfer')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('L')
                                            ->disabled()
                                            ->dehydrated(),
                                            
                                        Forms\Components\TextInput::make('storage_level_after')
                                            ->label('After Transfer')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('L')
                                            ->disabled()
                                            ->dehydrated(),
                                    ]),
                                    
                                Forms\Components\Fieldset::make('Truck Levels')
                                    ->schema([
                                        Forms\Components\TextInput::make('truck_level_before')
                                            ->label('Before Transfer')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('L')
                                            ->disabled()
                                            ->dehydrated(),
                                            
                                        Forms\Components\TextInput::make('truck_level_after')
                                            ->label('After Transfer')
                                            ->numeric()
                                            ->step(0.01)
                                            ->suffix('L')
                                            ->disabled()
                                            ->dehydrated(),
                                    ]),
                            ]),
                            
                        Forms\Components\Placeholder::make('transfer_validation')
                            ->label('Transfer Validation')
                            ->content(function ($get) {
                                $storageId = $get('fuel_storage_id');
                                $truckId = $get('fuel_truck_id');
                                $transferAmount = $get('transferred_amount') ?: 0;
                                
                                if (!$storageId || !$truckId || !$transferAmount) {
                                    return 'Complete all fields to see validation';
                                }
                                
                                $storage = FuelStorage::find($storageId);
                                $truck = FuelTruck::find($truckId);
                                
                                if (!$storage || !$truck) {
                                    return 'Invalid storage or truck selection';
                                }
                                
                                $messages = [];
                                
                                // Check storage availability
                                if ($storage->current_level < $transferAmount) {
                                    $messages[] = "⚠️ Insufficient fuel in storage (Available: {$storage->current_level}L)";
                                } else {
                                    $messages[] = "✅ Storage has sufficient fuel";
                                }
                                
                                // Check truck capacity
                                $availableCapacity = $truck->getRemainingCapacity();
                                if ($availableCapacity < $transferAmount) {
                                    $messages[] = "⚠️ Insufficient truck capacity (Available: {$availableCapacity}L)";
                                } else {
                                    $messages[] = "✅ Truck has sufficient capacity";
                                }
                                
                                // Check fuel type compatibility
                                if ($storage->fuel_type !== $truck->fuel_type) {
                                    $messages[] = "⚠️ Fuel type mismatch ({$storage->fuel_type} → {$truck->fuel_type})";
                                } else {
                                    $messages[] = "✅ Fuel types match";
                                }
                                
                                return implode("\n", $messages);
                            })
                            ->helperText('Real-time transfer validation'),
                    ])
                    ->collapsible(),
                    
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Additional notes about this transfer'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->label('Transfer #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->placeholder('Auto-generated'),
                    
                Tables\Columns\TextColumn::make('transfer_datetime')
                    ->label('Date & Time')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('fuelStorage.storage_code')
                    ->label('From Storage')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('fuelTruck.truck_code')
                    ->label('To Truck')
                    ->badge()
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('transferred_amount')
                    ->label('Amount')
                    ->suffix(' L')
                    ->numeric(2)
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('dailySession.shift.shift_name')
                    ->label('Shift')
                    ->badge()
                    ->color('secondary'),
                    
                Tables\Columns\TextColumn::make('operator_name')
                    ->label('Operator')
                    ->searchable()
                    ->limit(20),
                    
                Tables\Columns\TextColumn::make('efficiency_status')
                    ->label('Efficiency')
                    ->badge()
                    ->color(fn ($record) => $record->efficiency_color),
                    
                Tables\Columns\TextColumn::make('storage_level_change')
                    ->label('Storage Δ')
                    ->state(fn ($record) => number_format($record->getStorageLevelChange(), 2) . ' L')
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('truck_level_change')
                    ->label('Truck Δ')
                    ->state(fn ($record) => '+' . number_format($record->getTruckLevelChange(), 2) . ' L')
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('transfer_datetime')
                    ->form([
                        Forms\Components\DatePicker::make('transfer_date')
                            ->label('Transfer Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['transfer_date'], fn ($q) => $q->whereDate('transfer_datetime', $data['transfer_date']));
                    }),
                    
                Tables\Filters\SelectFilter::make('fuel_storage_id')
                    ->label('Storage')
                    ->relationship('fuelStorage', 'storage_name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('fuel_truck_id')
                    ->label('Truck')
                    ->relationship('fuelTruck', 'truck_name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('daily_session_id')
                    ->label('Session')
                    ->relationship('dailySession', 'session_name')
                    ->searchable(),
                    
                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Transfers')
                    ->query(fn ($query) => $query->whereDate('transfer_datetime', today())),
                    
                Tables\Filters\Filter::make('large_transfers')
                    ->label('Large Transfers (>1000L)')
                    ->query(fn ($query) => $query->where('transferred_amount', '>', 1000)),
                    
                Tables\Filters\Filter::make('efficiency_issues')
                    ->label('Efficiency Issues')
                    ->query(fn ($query) => $query->whereRaw('
                        ABS(storage_level_after - (storage_level_before - transferred_amount)) > 5 OR
                        ABS(truck_level_after - (truck_level_before + transferred_amount)) > 5
                    ')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->dailySession?->canBeModified() ?? true),
                Tables\Actions\Action::make('view_efficiency')
                    ->label('Efficiency Details')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('info')
                    ->modalContent(fn ($record) => view('filament.modals.transfer-efficiency', [
                        'transfer' => $record,
                        'efficiency' => $record->getTransferEfficiency(),
                        'storageChange' => $record->getStorageLevelChange(),
                        'truckChange' => $record->getTruckLevelChange()
                    ]))
                    ->modalWidth('3xl'),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure? This will rollback fuel levels and cannot be undone.')
                    ->visible(fn ($record) => $record->dailySession?->canBeModified() ?? true),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('export_transfers')
                        ->label('Export Selected')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function ($records) {
                            // Export logic would go here
                            \Filament\Notifications\Notification::make()
                                ->title('Export initiated')
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('This will rollback fuel levels for all selected transfers.'),
                ]),
            ])
            ->defaultSort('transfer_datetime', 'desc');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Transfer Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('transfer_number')
                                    ->label('Transfer Number')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->placeholder('Auto-generated'),
                                    
                                Infolists\Components\TextEntry::make('transfer_datetime')
                                    ->label('Date & Time')
                                    ->dateTime('d/m/Y H:i'),
                                    
                                Infolists\Components\TextEntry::make('operator_name')
                                    ->label('Operator')
                                    ->weight('bold'),
                            ]),
                            
                        Infolists\Components\TextEntry::make('dailySession.display_name')
                            ->label('Daily Session')
                            ->badge()
                            ->color('secondary'),
                    ]),
                    
                Infolists\Components\Section::make('Transfer Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('fuelStorage.display_name')
                                    ->label('From Storage')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('transferred_amount')
                                    ->label('Transfer Amount')
                                    ->suffix(' L')
                                    ->weight('bold')
                                    ->color('success'),
                                    
                                Infolists\Components\TextEntry::make('fuelTruck.display_name')
                                    ->label('To Truck')
                                    ->badge()
                                    ->color('warning'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Level Changes')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('storage_level_before')
                                        ->label('Storage Before')
                                        ->suffix(' L'),
                                    Infolists\Components\TextEntry::make('storage_level_after')
                                        ->label('Storage After')
                                        ->suffix(' L'),
                                    Infolists\Components\TextEntry::make('storage_level_change')
                                        ->label('Storage Change')
                                        ->state(fn ($record) => number_format($record->getStorageLevelChange(), 2) . ' L')
                                        ->color('danger')
                                        ->weight('bold'),
                                ])->columnSpan(1),
                                
                                Infolists\Components\Group::make([
                                    Infolists\Components\TextEntry::make('truck_level_before')
                                        ->label('Truck Before')
                                        ->suffix(' L'),
                                    Infolists\Components\TextEntry::make('truck_level_after')
                                        ->label('Truck After')
                                        ->suffix(' L'),
                                    Infolists\Components\TextEntry::make('truck_level_change')
                                        ->label('Truck Change')
                                        ->state(fn ($record) => '+' . number_format($record->getTruckLevelChange(), 2) . ' L')
                                        ->color('success')
                                        ->weight('bold'),
                                ])->columnSpan(1),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Transfer Efficiency')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('transfer_efficiency')
                                    ->label('Efficiency Score')
                                    ->state(fn ($record) => $record->getTransferEfficiency() . '%')
                                    ->badge()
                                    ->color(fn ($record) => $record->efficiency_color),
                                    
                                Infolists\Components\TextEntry::make('efficiency_status')
                                    ->label('Efficiency Status')
                                    ->badge()
                                    ->color(fn ($record) => $record->efficiency_color),
                                    
                                Infolists\Components\TextEntry::make('is_valid_transfer')
                                    ->label('Transfer Validity')
                                    ->state(fn ($record) => $record->isValidTransfer() ? 'Valid' : 'Invalid')
                                    ->badge()
                                    ->color(fn ($record) => $record->isValidTransfer() ? 'success' : 'danger'),
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
                                    ->label('Record Created')
                                    ->dateTime('d/m/Y H:i'),
                                    
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label('Last Updated')
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
            'index' => Pages\ListFuelTransfers::route('/'),
            'create' => Pages\CreateFuelTransfer::route('/create'),
            //'view' => Pages\ViewFuelTransfer::route('/{record}'),
            'edit' => Pages\EditFuelTransfer::route('/{record}/edit'),
        ];
    }
}