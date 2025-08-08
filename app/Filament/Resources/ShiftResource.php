<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftResource\Pages;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Work Shifts';
    
    protected static ?string $navigationGroup = 'Operations';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Shift Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('shift_code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(10)
                                    ->placeholder('e.g., PAGI, SORE, MALAM')
                                    ->helperText('Unique code for shift'),
                                    
                                Forms\Components\TextInput::make('shift_name')
                                    ->required()
                                    ->maxLength(50)
                                    ->placeholder('e.g., Shift Pagi, Shift Sore')
                                    ->helperText('Descriptive name for shift'),
                            ]),
                            
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('start_time')
                                    ->required()
                                    ->seconds(false)
                                    ->helperText('Shift start time'),
                                    
                                Forms\Components\TimePicker::make('end_time')
                                    ->required()
                                    ->seconds(false)
                                    ->helperText('Shift end time'),
                            ]),
                            
                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Optional description of this shift')
                            ->helperText('Additional details about shift responsibilities or notes'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Active shifts can have daily sessions created'),
                    ]),
                    
                Forms\Components\Section::make('Shift Preview')
                    ->schema([
                        Forms\Components\Placeholder::make('shift_info')
                            ->label('Shift Duration & Status')
                            ->content(function ($get) {
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                
                                if ($startTime && $endTime) {
                                    $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
                                    $end = \Carbon\Carbon::createFromFormat('H:i', $endTime);
                                    
                                    // Handle overnight shifts
                                    if ($end->lessThan($start)) {
                                        $end->addDay();
                                    }
                                    
                                    $duration = $start->diffInHours($end);
                                    $durationMinutes = $start->diffInMinutes($end) % 60;
                                    
                                    $timeRange = $startTime . ' - ' . $get('end_time');
                                    $durationText = $duration . 'h ' . $durationMinutes . 'm';
                                    
                                    $isOvernight = $get('end_time') < $get('start_time') ? ' (Overnight)' : '';
                                    
                                    return "Time Range: {$timeRange}{$isOvernight} | Duration: {$durationText}";
                                }
                                
                                return 'Enter start and end time to see shift details';
                            })
                            ->helperText('Real-time shift duration calculation'),
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
                Tables\Columns\TextColumn::make('shift_code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                    
                Tables\Columns\TextColumn::make('shift_name')
                    ->label('Shift Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('time_range')
                    ->label('Time Range')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('duration_in_hours')
                    ->label('Duration')
                    ->state(fn ($record) => $record->getDurationInHours() . ' hours')
                    ->badge()
                    ->color('secondary'),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status_color),
                    
                Tables\Columns\TextColumn::make('sessions_count_last_7_days')
                    ->label('Sessions (7d)')
                    ->state(fn ($record) => $record->getSessionsCount(now()->subDays(7), now()))
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('active_sessions_count')
                    ->label('Active Sessions')
                    ->state(fn ($record) => $record->getActiveSessionsCount())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                    
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
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All shifts')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                    
                Tables\Filters\Filter::make('currently_active')
                    ->label('Currently Active')
                    ->query(fn ($query) => $query->where(function ($q) {
                        $now = now()->format('H:i:s');
                        $q->where(function ($subQ) use ($now) {
                            // Regular shifts (start < end)
                            $subQ->whereRaw('start_time <= end_time')
                                 ->whereTime('start_time', '<=', $now)
                                 ->whereTime('end_time', '>=', $now);
                        })->orWhere(function ($subQ) use ($now) {
                            // Overnight shifts (start > end)
                            $subQ->whereRaw('start_time > end_time')
                                 ->where(function ($innerQ) use ($now) {
                                     $innerQ->whereTime('start_time', '<=', $now)
                                            ->orWhereTime('end_time', '>=', $now);
                                 });
                        });
                    })),
                    
                Tables\Filters\Filter::make('has_sessions_today')
                    ->label('Has Today\'s Sessions')
                    ->query(fn ($query) => $query->whereHas('dailySessions', fn ($q) => 
                        $q->where('session_date', today())
                    )),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('create_today_session')
                    ->label('Create Today\'s Session')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->action(function (Shift $record) {
                        $session = $record->getOrCreateTodaySession();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Today\'s session created successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(function (Shift $record) {
                        return $record->is_active && !$record->todaySession();
                    }),
                    
                Tables\Actions\Action::make('view_today_session')
                    ->label('View Today\'s Session')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function (Shift $record) {
                        $session = $record->todaySession();
                        if ($session) {
                            \Filament\Notifications\Notification::make()
                                ->title("Today's session: " . $session->display_name)
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(function (Shift $record) {
                        return $record->todaySession() !== null;
                    }),
                    
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to delete this shift? This will also affect related daily sessions.'),
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
                        
                    Tables\Actions\BulkAction::make('create_sessions_today')
                        ->label('Create Today\'s Sessions')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $created = 0;
                            foreach ($records->where('is_active', true) as $shift) {
                                if (!$shift->todaySession()) {
                                    $shift->getOrCreateTodaySession();
                                    $created++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title("Created {$created} session(s) for today")
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('start_time');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Shift Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('shift_code')
                                    ->label('Shift Code')
                                    ->weight('bold')
                                    ->color('primary'),
                                    
                                Infolists\Components\TextEntry::make('shift_name')
                                    ->label('Shift Name'),
                                    
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label('Active Status')
                                    ->boolean(),
                            ]),
                            
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('No description provided')
                            ->columnSpanFull(),
                    ]),
                    
                Infolists\Components\Section::make('Schedule Details')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('start_time')
                                    ->label('Start Time')
                                    ->time('H:i'),
                                    
                                Infolists\Components\TextEntry::make('end_time')
                                    ->label('End Time')
                                    ->time('H:i'),
                                    
                                Infolists\Components\TextEntry::make('duration_in_hours')
                                    ->label('Duration')
                                    ->state(fn ($record) => $record->getDurationInHours() . ' hours')
                                    ->badge()
                                    ->color('secondary'),
                                    
                                Infolists\Components\TextEntry::make('status')
                                    ->label('Current Status')
                                    ->badge()
                                    ->color(fn ($record) => $record->status_color),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Session Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('sessions_count_today')
                                    ->label('Today\'s Sessions')
                                    ->state(fn ($record) => $record->todaySession() ? 1 : 0)
                                    ->badge()
                                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                                    
                                Infolists\Components\TextEntry::make('sessions_count_7_days')
                                    ->label('Last 7 Days')
                                    ->state(fn ($record) => $record->getSessionsCount(now()->subDays(7), now()))
                                    ->badge()
                                    ->color('info'),
                                    
                                Infolists\Components\TextEntry::make('sessions_count_30_days')
                                    ->label('Last 30 Days')
                                    ->state(fn ($record) => $record->getSessionsCount(now()->subDays(30), now()))
                                    ->badge()
                                    ->color('warning'),
                                    
                                Infolists\Components\TextEntry::make('active_sessions_count')
                                    ->label('Active Sessions')
                                    ->state(fn ($record) => $record->getActiveSessionsCount())
                                    ->badge()
                                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                            ]),
                    ]),
                    
                Infolists\Components\Section::make('Recent Activity')
                    ->schema([
                        Infolists\Components\TextEntry::make('last_session')
                            ->label('Latest Session')
                            ->state(function ($record) {
                                $lastSession = $record->getLastSession();
                                return $lastSession ? 
                                    $lastSession->display_name . ' (' . $lastSession->status . ')' :
                                    'No sessions yet';
                            })
                            ->badge()
                            ->color('secondary'),
                            
                        Infolists\Components\TextEntry::make('today_session_status')
                            ->label('Today\'s Session')
                            ->state(function ($record) {
                                $todaySession = $record->todaySession();
                                return $todaySession ? 
                                    $todaySession->status . ' - ' . $todaySession->session_period :
                                    'No session created';
                            })
                            ->badge()
                            ->color(function ($record) {
                                $todaySession = $record->todaySession();
                                return $todaySession ? $todaySession->status_color : 'gray';
                            }),
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            //'view' => Pages\ViewShift::route('/{record}'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
        ];
    }
}