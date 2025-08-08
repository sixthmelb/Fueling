<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailySessionResource\Pages;
use App\Models\DailySession;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Carbon\Carbon;

class DailySessionResource extends Resource
{
    protected static ?string $model = DailySession::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Daily Sessions';
    
    protected static ?string $navigationGroup = 'Operations';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('session_date')
                                    ->required()
                                    ->default(today())
                                    ->helperText('Date for this session'),
                                    
                                Forms\Components\Select::make('shift_id')
                                    ->label('Shift')
                                    ->relationship('shift', 'shift_name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Select the work shift for this session'),
                            ]),
                            
                        Forms\Components\TextInput::make('session_name')
                            ->maxLength(100)
                            ->placeholder('Will be auto-generated if empty')
                            ->helperText('Optional custom name for session'),
                            
                        Forms\Components\Select::make('status')
                            ->options([
                                'Active' => 'Active',
                                'Closed' => 'Closed',
                            ])
                            ->default('Active')
                            ->required()
                            ->helperText('Session status'),
                    ]),
                    
                Forms\Components\Section::make('Session Schedule')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('start_datetime')
                                    ->required()
                                    ->seconds(false)
                                    ->default(function ($get) {
                                        $sessionDate = $get('session_date') ?? today();
                                        return Carbon::parse($sessionDate)->setTime(7, 0); // Default to 07:00
                                    })
                                    ->helperText('When this session starts'),
                                    
                                Forms\Components\DateTimePicker::make('end_datetime')
                                    ->seconds(false)
                                    ->after('start_datetime')
                                    ->helperText('When this session ends (leave empty for ongoing)'),
                            ]),
                            
                        Forms\Components\Placeholder::make('session_duration')
                            ->label('Session Duration')
                            ->content(function ($get) {
                                $start = $get('start_datetime');
                                $end = $get('end_datetime');
                                
                                if ($start && $end) {
                                    $startTime = Carbon::parse($start);
                                    $endTime = Carbon::parse($end);
                                    $duration = $startTime->diffForHumans($endTime, true);
                                    
                                    return "Duration: {$duration}";
                                } elseif ($start) {
                                    return "Started at: " . Carbon::parse($start)->format('d/m/Y H:i') . " (Ongoing)";
                                }
                                
                                return 'Enter start time to see duration';
                            })
                            ->helperText('Real-time session duration calculation'),
                    ])
                    ->live(),
                    
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Additional notes about this session'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('session_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('shift.shift_name')
                    ->label('Shift')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('session_name')
                    ->label('Session Name')
                    ->searchable()
                    ->limit(30),
                    
                Tables\Columns\TextColumn::make('session_period')
                    ->label('Time Period')
                    ->badge()
                    ->color('secondary'),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),
                    
                Tables\Columns\TextColumn::make('transfers_count')
                    ->label('Transfers')
                    ->counts('fuelTransfers')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('fuelTransactions')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('total_fuel_transfers')
                    ->label('Total Transfers')
                    ->state(fn ($record) => number_format($record->getTotalFuelTransfers(), 2) . ' L')
                    ->badge()
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('total_fuel_transactions')
                    ->label('Total Fuel Used')
                    ->state(fn ($record) => number_format($record->getTotalFuelTransactions(), 2) . ' L')
                    ->badge()
                    ->color('danger'),
                    
                Tables\Columns\TextColumn::make('unique_units_count')
                    ->label('Units Served')
                    ->state(fn ($record) => $record->getUniqueUnitsCount())
                    ->badge()
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('session_date')
                    ->form([
                        Forms\Components\DatePicker::make('session_date')
                            ->label('Session Date'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['session_date'], fn ($q) => $q->whereDate('session_date', $data['session_date']));
                    }),
                    
                Tables\Filters\SelectFilter::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'shift_name')
                    ->searchable()
                    ->preload(),
                    
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Active' => 'Active',
                        'Closed' => 'Closed',
                    ]),
                    
                Tables\Filters\Filter::make('today')
                    ->label('Today\'s Sessions')
                    ->query(fn ($query) => $query->where('session_date', today())),
                    
                Tables\Filters\Filter::make('this_week')
                    ->label('This Week')
                    ->query(fn ($query) => $query->whereBetween('session_date', [
                        now()->startOfWeek(),
                        now()->endOfWeek()
                    ])),
                    
                Tables\Filters\Filter::make('has_activity')
                    ->label('Has Activity')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->whereHas('fuelTransfers')
                          ->orWhereHas('fuelTransactions');
                    })),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('close_session')
                    ->label('Close Session')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('closing_notes')
                            ->label('Closing Notes')
                            ->placeholder('Optional notes about session closure')
                            ->rows(3),
                    ])
                    ->action(function (array $data, DailySession $record) {
                        $record->closeSession($data['closing_notes'] ?? null);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Session closed successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->isActive()),
                    
                Tables\Actions\Action::make('reopen_session')
                    ->label('Reopen Session')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to reopen this session?')
                    ->action(function (DailySession $record) {
                        $record->reopenSession();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Session reopened successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->isClosed()),
                    
                Tables\Actions\Action::make('view_statistics')
                    ->label('Statistics')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->modalContent(fn ($record) => view('filament.modals.session-statistics', [
                        'session' => $record,
                        'stats' => $record->getSessionStats(),
                        'mostActiveUnits' => $record->getMostActiveUnits()
                    ]))
                    ->modalWidth('5xl'),
                    
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this session? This will also delete all related transfers and transactions.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('close_sessions')
                        ->label('Close Selected Sessions')
                        ->icon('heroicon-o-lock-closed')
                        ->color('warning')
                        ->action(function ($records) {
                            $closed = 0;
                            foreach ($records->where('status', 'Active') as $session) {
                                $session->closeSession('Bulk closure');
                                $closed++;
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title("Closed {$closed} session(s)")
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\BulkAction::make('reopen_sessions')
                        ->label('Reopen Selected Sessions')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $reopened = 0;
                            foreach ($records->where('status', 'Closed') as $session) {
                                $session->reopenSession();
                                $reopened++;
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title("Reopened {$reopened} session(s)")
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('session_date', 'desc');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Session Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('session_date')
                                    ->label('Session Date')
                                    ->date('d/m/Y')
                                    ->weight('bold'),
                                    
                                Infolists\Components\TextEntry::make('shift.shift_name')
                                    ->label('Shift')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($record) => $record->status_color),
                            ]),
                            
                        Infolists\Components\TextEntry::make('session_name')
                            ->label('Session Name')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Schedule Details')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('start_datetime')
                                    ->label('Start Time')
                                    ->dateTime('d/m/Y H:i'),
                                    
                                Infolists\Components\TextEntry::make('end_datetime')
                                    ->label('End Time')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('Ongoing'),
                                    
                                Infolists\Components\TextEntry::make('session_duration')
                                    ->label('Duration')
                                    ->state(function ($record) {
                                        $duration = $record->getSessionDurationInHours();
                                        return $duration ? $duration . ' hours' : 'Ongoing';
                                    })
                                    ->badge()
                                    ->color('secondary'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Activity Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('transfers_count')
                                    ->label('Total Transfers')
                                    ->state(fn ($record) => $record->getTransfersCount())
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('transactions_count')
                                    ->label('Total Transactions')
                                    ->state(fn ($record) => $record->getTransactionsCount())
                                    ->badge()
                                    ->color('success'),
                                    
                                Infolists\Components\TextEntry::make('unique_units')
                                    ->label('Units Served')
                                    ->state(fn ($record) => $record->getUniqueUnitsCount())
                                    ->badge()
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('total_fuel_volume')
                                    ->label('Total Fuel Volume')
                                    ->state(fn ($record) => number_format($record->getTotalFuelTransfers() + $record->getTotalFuelTransactions(), 2) . ' L')
                                    ->badge()
                                    ->color('warning'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Fuel Movement Summary')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('total_transfers')
                                    ->label('Storage → Truck Transfers')
                                    ->state(fn ($record) => number_format($record->getTotalFuelTransfers(), 2) . ' L')
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('total_transactions')
                                    ->label('Fuel Consumed by Units')
                                    ->state(fn ($record) => number_format($record->getTotalFuelTransactions(), 2) . ' L')
                                    ->badge()
                                    ->color('danger'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Most Active Units')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('most_active_units')
                            ->label('')
                            ->state(fn ($record) => $record->getMostActiveUnits()->toArray())
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('unit.unit_code')
                                            ->label('Unit Code')
                                            ->weight('bold')
                                            ->color('primary'),
                                            
                                        Infolists\Components\TextEntry::make('transaction_count')
                                            ->label('Transactions')
                                            ->badge()
                                            ->color('success'),
                                            
                                        Infolists\Components\TextEntry::make('total_fuel')
                                            ->label('Total Fuel')
                                            ->state(fn ($state) => number_format($state, 2) . ' L')
                                            ->badge()
                                            ->color('warning'),
                                    ]),
                            ])
                            ->placeholder('No fuel transactions recorded'),
                    ])
                    ->collapsible()
                    ->collapsed(),
                    
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
            'index' => Pages\ListDailySessions::route('/'),
            'create' => Pages\CreateDailySession::route('/create'),
            //'view' => Pages\ViewDailySession::route('/{record}'),
            'edit' => Pages\EditDailySession::route('/{record}/edit'),
        ];
    }
    
    public static function getWidgets(): array
    {
        return [
            // We can add session-specific widgets here later
        ];
    }
}