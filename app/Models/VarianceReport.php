<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VarianceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_number',
        'report_date',
        'report_type',
        'period_start',
        'period_end',
        'total_system_fuel',
        'total_physical_fuel',
        'storage_variance',
        'truck_variance',
        'total_checks_performed',
        'critical_variances_count',
        'report_status',
        'summary_notes',
        'recommended_actions',
        'prepared_by',
        'reviewed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'total_system_fuel' => 'decimal:2',
        'total_physical_fuel' => 'decimal:2',
        'storage_variance' => 'decimal:2',
        'truck_variance' => 'decimal:2',
        'total_checks_performed' => 'integer',
        'critical_variances_count' => 'integer',
        'approved_at' => 'datetime',
    ];

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('report_status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('report_status', ['Draft', 'Final']);
    }

    public function scopeApproved($query)
    {
        return $query->where('report_status', 'Approved');
    }

    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('report_date', [$fromDate, $toDate]);
    }

    public function scopePeriodRange($query, $fromDate, $toDate)
    {
        return $query->where('period_start', '>=', $fromDate)
                    ->where('period_end', '<=', $toDate);
    }

    // Helper Methods
    public function generateReportNumber(): string
    {
        $date = $this->report_date->format('Ymd');
        $type = strtoupper(substr($this->report_type, 0, 1)); // D, W, M
        $sequence = static::where('report_type', $this->report_type)
                         ->whereDate('report_date', $this->report_date)
                         ->count() + 1;
        
        return "VRP-{$date}-{$type}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public static function generateForPeriod(string $reportType, Carbon $periodStart, Carbon $periodEnd): self
    {
        $report = new self([
            'report_type' => $reportType,
            'report_date' => now()->toDateString(),
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'report_status' => 'Draft',
            'prepared_by' => auth()->user()?->name ?? 'System',
        ]);
        
        $report->report_number = $report->generateReportNumber();
        
        // Calculate variance data
        $report->calculateVarianceData();
        
        return $report;
    }

    public function calculateVarianceData(): void
    {
        $stockChecks = PhysicalStockCheck::whereBetween('check_date', [$this->period_start, $this->period_end])->get();
        
        if ($stockChecks->isEmpty()) {
            $this->total_system_fuel = 0;
            $this->total_physical_fuel = 0;
            $this->storage_variance = 0;
            $this->truck_variance = 0;
            $this->total_checks_performed = 0;
            $this->critical_variances_count = 0;
            return;
        }

        // Calculate totals
        $this->total_system_fuel = $stockChecks->sum('system_level');
        $this->total_physical_fuel = $stockChecks->sum('physical_level');
        $this->total_checks_performed = $stockChecks->count();
        $this->critical_variances_count = $stockChecks->where('variance_status', 'Critical')->count();

        // Calculate variances by type
        $storageChecks = $stockChecks->where('checkable_type', FuelStorage::class);
        $truckChecks = $stockChecks->where('checkable_type', FuelTruck::class);
        
        $this->storage_variance = $storageChecks->sum(function ($check) {
            return $check->physical_level - $check->system_level;
        });

        $this->truck_variance = $truckChecks->sum(function ($check) {
            return $check->physical_level - $check->system_level;
        });

        $this->generateSummaryNotes($stockChecks);
        $this->generateRecommendedActions($stockChecks);
    }

    private function generateSummaryNotes($stockChecks): void
    {
        $totalVariance = $this->getTotalVariance();
        $variancePercentage = $this->getTotalVariancePercentage();
        
        $notes = [];
        $notes[] = "Period: {$this->period_start->format('d/m/Y')} to {$this->period_end->format('d/m/Y')}";
        $notes[] = "Total checks performed: {$this->total_checks_performed}";
        $notes[] = "Overall variance: " . number_format($totalVariance, 2) . "L (" . number_format($variancePercentage, 2) . "%)";
        
        if ($this->critical_variances_count > 0) {
            $notes[] = "Critical variances found: {$this->critical_variances_count}";
        }

        // Analyze patterns
        $normalCount = $stockChecks->where('variance_status', 'Normal')->count();
        $warningCount = $stockChecks->where('variance_status', 'Warning')->count();
        
        $notes[] = "Status breakdown: {$normalCount} Normal, {$warningCount} Warning, {$this->critical_variances_count} Critical";

        $this->summary_notes = implode("\n", $notes);
    }

    private function generateRecommendedActions($stockChecks): void
    {
        $actions = [];
        
        // Check for systematic issues
        if ($this->getTotalVariancePercentage() > 5) {
            $actions[] = "Investigate systematic variance issues - overall variance exceeds 5%";
        }

        if ($this->critical_variances_count > ($this->total_checks_performed * 0.1)) {
            $actions[] = "Review check procedures - high critical variance rate detected";
        }

        // Storage-specific actions
        if (abs($this->storage_variance) > 100) {
            $actions[] = "Investigate storage tank measurement accuracy";
        }

        // Truck-specific actions
        if (abs($this->truck_variance) > 50) {
            $actions[] = "Review mobile fuel truck measurement procedures";
        }

        // Pattern analysis
        $negativeVariances = $stockChecks->where('variance_amount', '<', -10)->count();
        if ($negativeVariances > ($this->total_checks_performed * 0.3)) {
            $actions[] = "Investigate potential fuel losses - high negative variance pattern";
        }

        if (empty($actions)) {
            $actions[] = "Continue current monitoring procedures - variances within acceptable limits";
        }

        $this->recommended_actions = implode("\n", $actions);
    }

    // Status Management
    public function finalize(): bool
    {
        if ($this->report_status !== 'Draft') {
            return false;
        }

        $this->update(['report_status' => 'Final']);
        return true;
    }

    public function approve(string $approverName): bool
    {
        if ($this->report_status !== 'Final') {
            return false;
        }

        $this->update([
            'report_status' => 'Approved',
            'approved_by' => $approverName,
            'approved_at' => now(),
        ]);

        return true;
    }

    public function reject(string $reason): bool
    {
        if ($this->report_status === 'Approved') {
            return false;
        }

        $this->update([
            'report_status' => 'Draft',
            'summary_notes' => $this->summary_notes . "\n\nRejected: " . $reason,
        ]);

        return true;
    }

    // Analysis Methods
    public function getTotalVariance(): float
    {
        return round($this->total_physical_fuel - $this->total_system_fuel, 2);
    }

    public function getTotalVariancePercentage(): float
    {
        if ($this->total_system_fuel == 0) {
            return 0;
        }
        
        return round(($this->getTotalVariance() / $this->total_system_fuel) * 100, 4);
    }

    public function getVarianceAccuracy(): string
    {
        $percentage = abs($this->getTotalVariancePercentage());
        
        return match (true) {
            $percentage <= 1 => 'Excellent',
            $percentage <= 3 => 'Good',
            $percentage <= 5 => 'Acceptable',
            default => 'Poor'
        };
    }

    public function getStorageVariancePercentage(): float
    {
    public function getStorageVariancePercentage(): float
    {
        $storageSystemTotal = PhysicalStockCheck::where('checkable_type', FuelStorage::class)
                                               ->whereBetween('check_date', [$this->period_start, $this->period_end])
                                               ->sum('system_level');
        
        if ($storageSystemTotal == 0) {
            return 0;
        }
        
        return round(($this->storage_variance / $storageSystemTotal) * 100, 4);
    }

    public function getTruckVariancePercentage(): float
    {
        $truckSystemTotal = PhysicalStockCheck::where('checkable_type', FuelTruck::class)
                                             ->whereBetween('check_date', [$this->period_start, $this->period_end])
                                             ->sum('system_level');
        
        if ($truckSystemTotal == 0) {
            return 0;
        }
        
        return round(($this->truck_variance / $truckSystemTotal) * 100, 4);
    }

    public function getCriticalVarianceRate(): float
    {
        if ($this->total_checks_performed == 0) {
            return 0;
        }
        
        return round(($this->critical_variances_count / $this->total_checks_performed) * 100, 2);
    }

    public function getVarianceByCheckMethod(): array
    {
        $checks = PhysicalStockCheck::whereBetween('check_date', [$this->period_start, $this->period_end])
                                   ->selectRaw('check_method, COUNT(*) as count, AVG(ABS(variance)) as avg_variance')
                                   ->groupBy('check_method')
                                   ->get();
        
        return $checks->mapWithKeys(function ($check) {
            return [
                $check->check_method => [
                    'count' => $check->count,
                    'avg_variance' => round($check->avg_variance, 2)
                ]
            ];
        })->toArray();
    }

    public function getTopVarianceItems($limit = 5): array
    {
        $checks = PhysicalStockCheck::with('checkable')
                                   ->whereBetween('check_date', [$this->period_start, $this->period_end])
                                   ->orderByDesc('variance')
                                   ->limit($limit)
                                   ->get();
        
        return $checks->map(function ($check) {
            return [
                'item' => $check->checkable_name,
                'type' => class_basename($check->checkable_type),
                'variance' => $check->variance_amount,
                'percentage' => $check->variance_percent,
                'status' => $check->variance_status,
                'date' => $check->check_date->format('d/m/Y')
            ];
        })->toArray();
    }

    public function compareWithPrevious(): ?array
    {
        $previousPeriod = $this->getPreviousPeriod();
        $previousReport = static::where('report_type', $this->report_type)
                               ->where('period_start', $previousPeriod['start'])
                               ->where('period_end', $previousPeriod['end'])
                               ->where('report_status', 'Approved')
                               ->first();

        if (!$previousReport) {
            return null;
        }

        return [
            'variance_change' => $this->getTotalVariance() - $previousReport->getTotalVariance(),
            'percentage_change' => $this->getTotalVariancePercentage() - $previousReport->getTotalVariancePercentage(),
            'checks_change' => $this->total_checks_performed - $previousReport->total_checks_performed,
            'critical_change' => $this->critical_variances_count - $previousReport->critical_variances_count,
            'trend' => $this->calculateTrend($previousReport)
        ];
    }

    private function getPreviousPeriod(): array
    {
        $periodLength = $this->period_start->diffInDays($this->period_end) + 1;
        $previousEnd = $this->period_start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($periodLength - 1);

        return [
            'start' => $previousStart,
            'end' => $previousEnd
        ];
    }

    private function calculateTrend($previousReport): string
    {
        $currentVariance = abs($this->getTotalVariancePercentage());
        $previousVariance = abs($previousReport->getTotalVariancePercentage());

        if ($currentVariance < $previousVariance * 0.8) {
            return 'Improving';
        } elseif ($currentVariance > $previousVariance * 1.2) {
            return 'Declining';
        } else {
            return 'Stable';
        }
    }

    // Export Methods
    public function getReportData(): array
    {
        return [
            'header' => [
                'report_number' => $this->report_number,
                'report_date' => $this->report_date->format('d/m/Y'),
                'period' => $this->period_start->format('d/m/Y') . ' - ' . $this->period_end->format('d/m/Y'),
                'type' => $this->report_type,
                'status' => $this->report_status,
                'prepared_by' => $this->prepared_by,
                'approved_by' => $this->approved_by,
                'approved_at' => $this->approved_at?->format('d/m/Y H:i'),
            ],
            'summary' => [
                'total_checks' => $this->total_checks_performed,
                'total_variance' => $this->getTotalVariance(),
                'variance_percentage' => $this->getTotalVariancePercentage(),
                'critical_count' => $this->critical_variances_count,
                'accuracy_rating' => $this->getVarianceAccuracy(),
                'storage_variance' => $this->storage_variance,
                'truck_variance' => $this->truck_variance,
            ],
            'analysis' => [
                'variance_by_method' => $this->getVarianceByCheckMethod(),
                'top_variances' => $this->getTopVarianceItems(),
                'previous_comparison' => $this->compareWithPrevious(),
            ],
            'notes' => [
                'summary' => $this->summary_notes,
                'recommendations' => $this->recommended_actions,
            ]
        ];
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return $this->report_number ?: "Report #{$this->id}";
    }

    public function getPeriodDescriptionAttribute(): string
    {
        return $this->period_start->format('d/m/Y') . ' - ' . $this->period_end->format('d/m/Y');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->report_status) {
            'Draft' => 'warning',
            'Final' => 'primary',
            'Approved' => 'success',
            default => 'secondary'
        };
    }

    public function getAccuracyColorAttribute(): string
    {
        return match ($this->getVarianceAccuracy()) {
            'Excellent' => 'success',
            'Good' => 'primary',
            'Acceptable' => 'warning',
            'Poor' => 'danger',
            default => 'secondary'
        };
    }

    public function getVarianceDescriptionAttribute(): string
    {
        $variance = $this->getTotalVariance();
        $percentage = $this->getTotalVariancePercentage();
        
        if ($variance > 0) {
            return "Surplus: +{$variance}L ({$percentage}%)";
        } elseif ($variance < 0) {
            return "Deficit: {$variance}L ({$percentage}%)";
        } else {
            return "Balanced";
        }
    }

    public function getCanBeEditedAttribute(): bool
    {
        return $this->report_status === 'Draft';
    }

    public function getCanBeFinalizedAttribute(): bool
    {
        return $this->report_status === 'Draft';
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->report_status === 'Final';
    }

    public function getRequiresAttentionAttribute(): bool
    {
        return abs($this->getTotalVariancePercentage()) > 5 || 
               $this->getCriticalVarianceRate() > 10;
    }

    public function getTrendColorAttribute(): string
    {
        $comparison = $this->compareWithPrevious();
        
        if (!$comparison) {
            return 'secondary';
        }

        return match ($comparison['trend']) {
            'Improving' => 'success',
            'Stable' => 'info',
            'Declining' => 'danger',
            default => 'secondary'
        };
    }

    public function getFormattedReportDateAttribute(): string
    {
        return $this->report_date->format('d/m/Y');
    }
}