<?php

namespace App\Filament\Widgets;

use App\Models\FuelTransaction;
use App\Models\FuelTransfer;
use App\Models\Unit;
use App\Models\UnitType;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DailyConsumptionChart extends ChartWidget
{
    protected static ?string $heading = 'Daily Fuel Consumption';
    
    protected static ?int $sort = 2;
    
    protected static ?string $pollingInterval = '30s';
    
    public ?string $filter = 'today';
    
    protected function getData(): array
    {
        $period = $this->getPeriodDates();
        
        return match ($this->filter) {
            'today' => $this->getTodayHourlyData(),
            'week' => $this->getWeeklyData($period),
            'month' => $this->getMonthlyData($period),
            default => $this->getTodayHourlyData()
        };
    }
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getFilters(): ?array
    {
        return [
            'today' => 'Today (Hourly)',
            'week' => 'This Week', 
            'month' => 'This Month',
        ];
    }
    
    protected function getPeriodDates(): array
    {
        return match ($this->filter) {
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek()
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth()
            ],
            default => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay()
            ]
            };
    }
    
    /**
     * Get database-specific hour extraction query
     */
    protected function getHourQuery($columnName = 'transaction_datetime'): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        switch ($driver) {
            case 'mysql':
                return "HOUR({$columnName})";
            case 'sqlite':
                return "CAST(strftime('%H', {$columnName}) AS INTEGER)";
            case 'pgsql':
                return "EXTRACT(hour FROM {$columnName})";
            default:
                return "CAST(strftime('%H', {$columnName}) AS INTEGER)";
        }
    }
    
    /**
     * Get database-specific date extraction query
     */
    protected function getDateQuery($columnName = 'transaction_datetime'): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        switch ($driver) {
            case 'mysql':
                return "DATE({$columnName})";
            case 'sqlite':
                return "DATE({$columnName})";
            case 'pgsql':
                return "DATE({$columnName})";
            default:
                return "DATE({$columnName})";
        }
    }
    
    /**
     * Get database-specific week extraction query
     */
    protected function getWeekQuery($columnName = 'transaction_datetime'): string
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        
        switch ($driver) {
            case 'mysql':
                return "WEEK({$columnName}, 1)";
            case 'sqlite':
                return "CAST(strftime('%W', {$columnName}) AS INTEGER)";
            case 'pgsql':
                return "EXTRACT(week FROM {$columnName})";
            default:
                return "CAST(strftime('%W', {$columnName}) AS INTEGER)";
        }
    }
    
    protected function getTodayHourlyData(): array
    {
        $transactionHourQuery = $this->getHourQuery('transaction_datetime');
        $transferHourQuery = $this->getHourQuery('transfer_datetime');
        
        $hourlyTransactions = FuelTransaction::whereDate('transaction_datetime', today())
            ->selectRaw("{$transactionHourQuery} as hour, SUM(fuel_amount) as total_fuel, COUNT(*) as transaction_count")
            ->groupBy(DB::raw($transactionHourQuery))
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
            
        $hourlyTransfers = FuelTransfer::whereDate('transfer_datetime', today())
            ->selectRaw("{$transferHourQuery} as hour, SUM(transferred_amount) as total_transferred")
            ->groupBy(DB::raw($transferHourQuery))
            ->orderBy('hour')
            ->get()
            ->keyBy('hour');
        
        $consumptionData = [];
        $transferData = [];
        $labels = [];
        
        // Generate data for each hour of the day
        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = sprintf('%02d:00', $hour);
            $consumptionData[] = $hourlyTransactions->get($hour)?->total_fuel ?? 0;
            $transferData[] = $hourlyTransfers->get($hour)?->total_transferred ?? 0;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Fuel Consumption (L)',
                    'data' => $consumptionData,
                    'borderColor' => 'rgb(239, 68, 68)', // Red
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
                [
                    'label' => 'Fuel Transfers (L)', 
                    'data' => $transferData,
                    'borderColor' => 'rgb(59, 130, 246)', // Blue
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ]
            ],
            'labels' => $labels,
        ];
    }
    
    protected function getWeeklyData($period): array
    {
        $transactionDateQuery = $this->getDateQuery('transaction_datetime');
        $transferDateQuery = $this->getDateQuery('transfer_datetime');
        
        $dailyTransactions = FuelTransaction::whereBetween('transaction_datetime', [$period['start'], $period['end']])
            ->selectRaw("{$transactionDateQuery} as date, SUM(fuel_amount) as total_fuel")
            ->groupBy(DB::raw($transactionDateQuery))
            ->orderBy('date')
            ->get()
            ->keyBy('date');
            
        $dailyTransfers = FuelTransfer::whereBetween('transfer_datetime', [$period['start'], $period['end']])
            ->selectRaw("{$transferDateQuery} as date, SUM(transferred_amount) as total_transferred")
            ->groupBy(DB::raw($transferDateQuery))
            ->orderBy('date')
            ->get()
            ->keyBy('date');
        
        $consumptionData = [];
        $transferData = [];
        $labels = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $period['start']->copy()->addDays($i);
            $dateStr = $date->toDateString();
            
            $labels[] = $date->format('M j');
            $consumptionData[] = $dailyTransactions->get($dateStr)?->total_fuel ?? 0;
            $transferData[] = $dailyTransfers->get($dateStr)?->total_transferred ?? 0;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Daily Consumption (L)',
                    'data' => $consumptionData,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
                [
                    'label' => 'Daily Transfers (L)',
                    'data' => $transferData,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ]
            ],
            'labels' => $labels,
        ];
    }
    
    protected function getMonthlyData($period): array
    {
        // For SQLite compatibility, we'll use a different approach
        // Instead of WEEK function, we'll group by date and then organize by weeks
        $transactionDateQuery = $this->getDateQuery('transaction_datetime');
        $transferDateQuery = $this->getDateQuery('transfer_datetime');
        
        $dailyData = FuelTransaction::whereBetween('transaction_datetime', [$period['start'], $period['end']])
            ->selectRaw("{$transactionDateQuery} as date, SUM(fuel_amount) as total_fuel")
            ->groupBy(DB::raw($transactionDateQuery))
            ->orderBy('date')
            ->get();
            
        $dailyTransfers = FuelTransfer::whereBetween('transfer_datetime', [$period['start'], $period['end']])
            ->selectRaw("{$transferDateQuery} as date, SUM(transferred_amount) as total_transferred")
            ->groupBy(DB::raw($transferDateQuery))
            ->orderBy('date')
            ->get();
        
        // Group daily data into weeks
        $weeklyConsumption = [];
        $weeklyTransfers = [];
        $labels = [];
        
        $current = $period['start']->copy()->startOfWeek();
        $weekNumber = 1;
        
        while ($current->lte($period['end'])) {
            $weekEnd = $current->copy()->endOfWeek();
            if ($weekEnd->gt($period['end'])) {
                $weekEnd = $period['end'];
            }
            
            $labels[] = 'Week ' . $weekNumber;
            
            // Sum consumption for this week
            $weekConsumption = $dailyData->filter(function ($item) use ($current, $weekEnd) {
                $itemDate = \Carbon\Carbon::parse($item->date);
                return $itemDate->gte($current) && $itemDate->lte($weekEnd);
            })->sum('total_fuel');
            
            // Sum transfers for this week  
            $weekTransfer = $dailyTransfers->filter(function ($item) use ($current, $weekEnd) {
                $itemDate = \Carbon\Carbon::parse($item->date);
                return $itemDate->gte($current) && $itemDate->lte($weekEnd);
            })->sum('total_transferred');
            
            $weeklyConsumption[] = $weekConsumption;
            $weeklyTransfers[] = $weekTransfer;
            
            $current->addWeek();
            $weekNumber++;
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Weekly Consumption (L)',
                    'data' => $weeklyConsumption,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
                [
                    'label' => 'Weekly Transfers (L)',
                    'data' => $weeklyTransfers,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ]
            ],
            'labels' => $labels,
        ];
    }
    
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Fuel (Liters)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => $this->getXAxisLabel(),
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
            'maintainAspectRatio' => false,
        ];
    }
    
    protected function getXAxisLabel(): string
    {
        return match ($this->filter) {
            'today' => 'Hour of Day',
            'week' => 'Day of Week',
            'month' => 'Week of Month',
            default => 'Time Period'
        };
    }
    
    public static function canView(): bool
    {
        return true;
    }
    
    public function getDescription(): ?string
    {
        $totalToday = FuelTransaction::whereDate('transaction_datetime', today())->sum('fuel_amount');
        $transfersToday = FuelTransfer::whereDate('transfer_datetime', today())->sum('transferred_amount');
        
        return 'Today: ' . number_format($totalToday, 0) . 'L consumed, ' . number_format($transfersToday, 0) . 'L transferred';
    }
}