<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FuelTruckResource\Pages;
use App\Models\FuelTruck;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class FuelTruckResource extends Resource
{
    protected static ?string $model = FuelTruck::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Fuel Trucks';
    
    protected static ?string $navigationGroup = 'Master Data';
    
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Truck Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('truck_code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->placeholder('e.g., FT-001, MOBILE-01')
                                    ->helperText('Unique identifier for fuel truck'),
                                    
                                Forms\Components\TextInput::make('truck_name')
                                    ->required()
                                    ->maxLength(100)
                                    ->placeholder('e.g., Mobile Fuel Truck #1')
                                    ->helperText('Descriptive name for truck'),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('license_plate')
                                    ->maxLength(20)
                                    ->placeholder('e.g., B 1234 XYZ')
                                    ->helperText('Vehicle license plate number'),
                                    
                                Forms\Components\TextInput::make('driver_name')
                                    ->maxLength(100)
                                    ->placeholder('e.g., John Doe')
                                    ->helperText('Primary driver name'),
                            ]),
                            
                        Forms\Components\Select::make('fuel_type')
                            ->options([
                                'Solar' => 'Solar (Diesel)',
                                'Bensin' => 'Bensin (Gasoline)',
                                'Pertamax' => 'Pertamax (Premium)',
                            ])
                            ->default('Solar')
                            ->required()
                            ->helperText('Type of fuel this truck distributes'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Active trucks can receive and distribute fuel'),
                    ]),
                    
                Forms\Components\Section::make('Tank Specifications')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('capacity')
                                    ->label('Tank Capacity')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0.01)
                                    ->suffix('L')
                                    ->helperText('Maximum fuel tank capacity'),
                                    
                                Forms\Components\TextInput::make('current_level')
                                    ->label('Current Level')
                                    ->required()
                                    ->numeric()
                                    ->step(0.01)
                                    ->minValue(0)
                                    ->suffix('L')
                                    ->default(0)
                                    ->helperText('Current fuel level in tank'),
                            ]),
                            
                        Forms\Components\Placeholder::make('tank_info')
                            ->label('Tank Information')
                            ->content(function ($get) {
                                $capacity = $get('capacity') ?: 0;
                                $current = $get('current_level') ?: 0;
                                
                                if ($capacity > 0) {
                                    $percentage = round(($current / $capacity) * 100, 2);
                                    $remaining = $capacity - $current;
                                    
                                    $status = match (true) {
                                        $current <= 0 => 'Empty',
                                        $current >= $capacity => 'Full',
                                        $percentage >= 75 => 'High',
                                        $percentage >= 25 => 'Medium',
                                        default => 'Low'
                                    };
                                    
                                    return "Status: {$status} | Usage: {$percentage}% ({$current}L / {$capacity}L) | Available: {$remaining}L";
                                }
                                
                                return 'Enter capacity and current level to see tank info';
                            })
                            ->helperText('Real-time tank status calculation'),
                    ]),
                    
                Forms\Components\Section::make('Vehicle Specifications')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('brand')
                                    ->maxLength(50)
                                    ->placeholder('e.g., Hino, Mitsubishi'),
                                    
                                Forms\Components\TextInput::make('model')
                                    ->maxLength(50)
                                    ->placeholder('e.g., Ranger, Canter'),
                                    
                                Forms\Components\TextInput::make('manufacture_year')
                                    ->label('Year')
                                    ->numeric()
                                    ->minValue(1990)
                                    ->maxValue(date('Y'))
                                    ->placeholder(date('Y')),
                            ]),
                            
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Additional notes about this truck'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ])
            ->live();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('truck_code')
                    ->label('Truck Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('truck_name')
                    ->label('Truck Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('license_plate')
                    ->label('License Plate')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                    
                Tables\Columns\TextColumn::make('driver_name')
                    ->label('Driver')
                    ->searchable()
                    ->limit(20),
                    
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
                    ->color(fn ($record) => match (true) {
                        $record->isEmpty() => 'danger',
                        $record->isFull() => 'success',
                        $record->getCapacityUsagePercentage() >= 75 => 'success',
                        $record->getCapacityUsagePercentage() >= 25 => 'warning',
                        default => 'danger'
                    }),
                    
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
                        $record->isEmpty() => 'danger',
                        $record->isFull() => 'success', 
                        $record->getCapacityUsagePercentage() >= 75 => 'success',
                        $record->getCapacityUsagePercentage() >= 25 => 'warning',
                        default => 'danger'
                    }),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),
                    
                Tables\Columns\TextColumn::make('todayTotalReceived')
                    ->label("Today's Received")
                    ->state(fn ($record) => number_format($record->todayTotalReceived(), 2) . ' L')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('todayTotalDistributed')
                    ->label("Today's Distributed")
                    ->state(fn ($record) => number_format($record->todayTotalDistributed(), 2) . ' L')
                    ->badge()
                    ->color('warning'),
                    
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
                    ->placeholder('All trucks')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                    
                Tables\Filters\Filter::make('empty_trucks')
                    ->label('Empty Trucks')
                    ->query(fn ($query) => $query->where('current_level', '<=', 0)),
                    
                Tables\Filters\Filter::make('full_trucks')
                    ->label('Full Trucks')
                    ->query(fn ($query) => $query->whereRaw('current_level >= capacity')),
                    
                Tables\Filters\SelectFilter::make('driver_name')
                    ->label('Driver')
                    ->options(fn () => FuelTruck::whereNotNull('driver_name')
                        ->distinct()
                        ->pluck('driver_name', 'driver_name')
                        ->toArray())
                    ->searchable(),
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
                            ->placeholder('e.g., Physical measurement correction, maintenance adjustment'),
                    ])
                    ->action(function (array $data, FuelTruck $record) {
                        $oldLevel = $record->current_level;
                        $record->updateLevel($data['new_level']);
                        
                        // Proper activity logging dengan custom ActivityLog
                        \App\Models\ActivityLog::createLog(
                            'fuel_truck_adjustment',
                            'Truck fuel level manually adjusted',
                            $record,
                            auth()->user(),
                            [
                                'truck_code' => $record->truck_code,
                                'old_level' => $oldLevel,
                                'new_level' => $data['new_level'],
                                'difference' => $data['new_level'] - $oldLevel,
                                'reason' => $data['adjustment_reason']
                            ]
                        );
                        
                        // Show success notification
                        \Filament\Notifications\Notification::make()
                            ->title('Truck level adjusted successfully')
                            ->body("Level changed from {$oldLevel}L to {$data['new_level']}L")
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->is_active),
                    
                Tables\Actions\Action::make('empty_truck')
                    ->label('Empty Truck')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will set the truck fuel level to 0. Are you sure?')
                    ->action(fn (FuelTruck $record) => $record->updateLevel(0))
                    ->visible(fn ($record) => $record->current_level > 0),
                    
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this truck? This action cannot be undone.'),
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
            ->defaultSort('truck_code');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Truck Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('truck_code')
                                    ->label('Truck Code')
                                    ->weight('bold')
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('truck_name')
                                    ->label('Truck Name'),
                                    
                                Infolists\Components\TextEntry::make('license_plate')
                                    ->label('License Plate')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('Not specified'),
                            ]),
                            
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('driver_name')
                                    ->label('Primary Driver')
                                    ->placeholder('Not assigned'),
                                    
                                Infolists\Components\TextEntry::make('fuel_type')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Solar' => 'success',
                                        'Bensin' => 'warning',
                                        'Pertamax' => 'info',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Tank Status')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('capacity')
                                    ->label('Tank Capacity')
                                    ->suffix(' L')
                                    ->weight('bold'),
                                    
                                Infolists\Components\TextEntry::make('current_level')
                                    ->label('Current Level')
                                    ->suffix(' L')
                                    ->color(fn ($record) => match (true) {
                                        $record->isEmpty() => 'danger',
                                        $record->isFull() => 'success',
                                        $record->getCapacityUsagePercentage() >= 75 => 'success',
                                        $record->getCapacityUsagePercentage() >= 25 => 'warning',
                                        default => 'danger'
                                    }),
                                    
                                Infolists\Components\TextEntry::make('remaining_capacity')
                                    ->label('Remaining Capacity')
                                    ->state(fn ($record) => number_format($record->getRemainingCapacity(), 2))
                                    ->suffix(' L'),
                                    
                                Infolists\Components\TextEntry::make('capacity_usage_percentage')
                                    ->label('Usage Percentage')
                                    ->state(fn ($record) => $record->getCapacityUsagePercentage() . '%')
                                    ->badge()
                                    ->color(fn ($record) => $record->status_color),
                            ]),
                            
                        Infolists\Components\TextEntry::make('status')
                            ->label('Tank Status')
                            ->badge()
                            ->color(fn ($record) => $record->status_color),
                    ]),
                    
                Infolists\Components\Section::make('Vehicle Specifications')
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
                            
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No additional notes')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Activity Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('todayTotalReceived')
                                    ->label("Today's Fuel Received")
                                    ->state(fn ($record) => number_format($record->todayTotalReceived(), 2) . ' L')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('todayTotalDistributed')
                                    ->label("Today's Fuel Distributed")
                                    ->state(fn ($record) => number_format($record->todayTotalDistributed(), 2) . ' L')
                                    ->badge()
                                    ->color('warning'),
                                    
                                Infolists\Components\TextEntry::make('latest_transfer')
                                    ->label('Latest Transfer')
                                    ->state(fn ($record) => $record->getLatestTransfer()?->formatted_date_time ?? 'No transfers')
                                    ->badge()
                                    ->color('secondary'),
                                    
                                Infolists\Components\TextEntry::make('latest_distribution')
                                    ->label('Latest Distribution')
                                    ->state(fn ($record) => $record->getLatestDistribution()?->formatted_date_time ?? 'No distributions')
                                    ->badge()
                                    ->color('secondary'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Status Information')
                    ->schema([
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
            'index' => Pages\ListFuelTrucks::route('/'),
            'create' => Pages\CreateFuelTruck::route('/create'),
            //'view' => Pages\ViewFuelTruck::route('/{record}'),
            'edit' => Pages\EditFuelTruck::route('/{record}/edit'),
        ];
    }
}