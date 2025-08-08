<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PhysicalStockCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_number',
        'checkable_type',
        'checkable_id',
        'check_date',
        'check_time',
        'system_level',
        'physical_level',
        'checker_name',
        'check_method',
        'variance_status',
        'notes',
        'corrective_action',
        'system_adjusted',
        'adjustment_amount',
    ];

    protected $casts = [
        'check_date' => 'date',
        'check_time' => 'datetime:H:i',
        'system_level' => 'decimal:2',
        'physical_level' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'system_adjusted' => 'boolean',
    ];

    // Relationships
    public function checkable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->where('check_date', today());
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('variance_status', $status);
    }

    public function scopeByChecker($query, $checkerName)
    {
        return $query->where('checker_name', 'like', "%{$checkerName}%");
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('check_method', $method);
    }

    public function scopeSystemAdjusted($query)
    {
        return $query->where('system_adjusted', true);
    }

    public function scopeNeedsAdjustment($query)
    {
        return $query->where('system_adjusted', false)
                    ->whereIn('variance_status', ['Warning', 'Critical']);
    }

    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('check_date', [$fromDate, $toDate]);
    }

    public function scopeCriticalVariance($query)
    {
        return $query->where('variance_status', 'Critical');
    }

    public function scopeForCheckableType($query, $type)
    {
        return $query->where('checkable_type', $type);
    }

    // MYSQL FIX: Accessor methods for computed columns
    public function getVarianceAttribute(): float
    {
        return $this->physical_level - $this->system_level;
    }

    public function getVariancePercentageAttribute(): float
    {
        if ($this->system_level == 0) {
            return 0;
        }
        return round(($this->variance / $this->system_level) * 100, 4);
    }

    public function getCheckDatetimeAttribute(): string
    {
        return $this->check_date->format('Y-m-d') . ' ' . $this->check_time->format('H:i:s');
    }

    // Helper Methods
    public function generateCheckNumber(): string
    {
        $date = $this->check_date->format('Ymd');
        $type = strtoupper(substr(class_basename($this->checkable_type), 0, 3));
        $sequence = static::whereDate('check_date', $this->check_date)->count() + 1;
        
        return "CHK-{$date}-{$type}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public function getVariance(): float
    {
        return $this->variance;
    }

    public function getVariancePercentage(): float
    {
        return $this->variance_percentage;
    }

    // IMPROVED: Variance status calculation with more granular levels
    public function calculateVarianceStatus(): string
    {
        $variancePercentage = abs($this->getVariancePercentage());
        $varianceAmount = abs($this->getVariance());
        
        // Use both percentage and absolute amount for better classification
        if ($variancePercentage <= 1 && $varianceAmount <= 5) {
            return 'Normal';
        } elseif ($variancePercentage <= 3 && $varianceAmount <= 25) {
            return 'Minor';
        } elseif ($variancePercentage <= 5 && $varianceAmount <= 50) {
            return 'Warning';
        } else {
            return 'Critical';
        }
    }

    public function updateVarianceStatus(): void
    {
        $oldStatus = $this->variance_status;
        $newStatus = $this->calculateVarianceStatus();
        
        if ($oldStatus !== $newStatus) {
            $this->variance_status = $newStatus;
            $this->save();
            
            Log::info('Variance status updated', [
                'check_id' => $this->id,
                'checkable' => $this->checkable_name,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'variance' => $this->getVariance(),
                'variance_percentage' => $this->getVariancePercentage()
            ]);
        }
    }

    // IMPROVED: System adjustment with comprehensive validation
    public function adjustSystem(string $reason = null): bool
    {
        if ($this->system_adjusted) {
            Log::warning('Attempt to adjust already adjusted stock check', [
                'check_id' => $this->id,
                'checkable' => $this->checkable_name
            ]);
            return false;
        }

        $adjustmentAmount = $this->getVariance();
        
        // Validate adjustment amount
        if (abs($adjustmentAmount) < 0.01) {
            Log::info('No adjustment needed - variance too small', [
                'check_id' => $this->id,
                'variance' => $adjustmentAmount
            ]);
            return false;
        }
        
        // Update the checkable item's level
        if ($this->checkable && method_exists($this->checkable, 'updateLevel')) {
            $oldLevel = $this->checkable->current_level;
            $success = $this->checkable->updateLevel($this->physical_level);
            
            if ($success) {
                $this->update([
                    'system_adjusted' => true,
                    'adjustment_amount' => $adjustmentAmount,
                    'corrective_action' => $reason ? 
                        ($this->corrective_action . "\n" . $reason) : 
                        ($this->corrective_action . "\nSystem adjusted to physical level: {$this->physical_level}L"),
                ]);
                
                Log::info('System level adjusted based on stock check', [
                    'check_id' => $this->id,
                    'checkable' => $this->checkable_name,
                    'old_system_level' => $oldLevel,
                    'new_system_level' => $this->physical_level,
                    'adjustment_amount' => $adjustmentAmount,
                    'reason' => $reason
                ]);
                
                return true;
            } else {
                Log::error('Failed to update checkable level', [
                    'check_id' => $this->id,
                    'checkable' => $this->checkable_name,
                    'target_level' => $this->physical_level
                ]);
            }
        } else {
            Log::error('Checkable does not support level updates', [
                'check_id' => $this->id,
                'checkable_type' => $this->checkable_type,
                'checkable_id' => $this->checkable_id
            ]);
        }
        
        return false;
    }

    public function requiresAttention(): bool
    {
        return !$this->system_adjusted && in_array($this->variance_status, ['Warning', 'Critical']);
    }

    public function canBeAdjusted(): bool
    {
        return !$this->system_adjusted && 
               abs($this->getVariance()) >= 0.01 && 
               $this->checkable &&
               method_exists($this->checkable, 'updateLevel');
    }

    // Analysis Methods
    public function getCheckAccuracy(): string
    {
        $variancePercentage = abs($this->getVariancePercentage());
        
        return match (true) {
            $variancePercentage <= 0.5 => 'Excellent',
            $variancePercentage <= 1 => 'Very Good',
            $variancePercentage <= 2 => 'Good',
            $variancePercentage <= 5 => 'Fair',
            default => 'Poor'
        };
    }

    public function isPossibleTheft(): bool
    {
        // Significant negative variance might indicate theft
        $variance = $this->getVariance();
        $percentage = $this->getVariancePercentage();
        
        return $variance < -10 && $percentage < -5;
    }

    public function isPossibleLeakage(): bool
    {
        // Check for pattern of negative variances in recent checks
        $recentChecks = static::where('checkable_type', $this->checkable_type)
                             ->where('checkable_id', $this->checkable_id)
                             ->where('check_date', '>=', now()->subDays(14))
                             ->where('id', '!=', $this->id)
                             ->get();
        
        if ($recentChecks->count() < 2) {
            return false;
        }
        
        $negativeCount = $recentChecks->filter(function ($check) {
            return $check->getVariance() < -5;
        })->count();
        
        return ($negativeCount / $recentChecks->count()) >= 0.6; // 60% of recent checks are negative
    }

    public function isPossibleMeasurementError(): bool
    {
        // Large variance might indicate measurement error
        $variancePercentage = abs($this->getVariancePercentage());
        $varianceAmount = abs($this->getVariance());
        
        return $variancePercentage > 10 || $varianceAmount > 100;
    }

    public function getRecommendedAction(): string
    {
        if ($this->system_adjusted) {
            return 'Completed - System adjusted';
        }
        
        $variance = $this->getVariance();
        $variancePercentage = $this->getVariancePercentage();
        
        if ($this->isPossibleTheft()) {
            return 'Investigate possible theft or unauthorized usage';
        }
        
        if ($this->isPossibleLeakage()) {
            return 'Check for leakage or systematic losses';
        }
        
        if ($this->isPossibleMeasurementError()) {
            return 'Verify measurement accuracy and recalibrate if needed';
        }
        
        if (abs($variancePercentage) > 5 || abs($variance) > 25) {
            return 'Adjust system level to match physical measurement';
        }
        
        if (abs($variancePercentage) > 2 || abs($variance) > 10) {
            return 'Monitor closely and investigate if pattern continues';
        }
        
        return 'No action required - variance within acceptable limits';
    }

    // Historical Analysis
    public function getHistoricalPattern(): array
    {
        $historicalChecks = static::where('checkable_type', $this->checkable_type)
                                 ->where('checkable_id', $this->checkable_id)
                                 ->where('check_date', '>=', now()->subDays(30))
                                 ->orderBy('check_date')
                                 ->get();
        
        if ($historicalChecks->count() < 3) {
            return [
                'pattern' => 'Insufficient Data',
                'trend' => 'Unknown',
                'average_variance' => 0,
                'variance_trend' => 'Stable'
            ];
        }
        
        $variances = $historicalChecks->map(fn($check) => $check->getVariance());
        $avgVariance = $variances->avg();
        $recentVariances = $variances->slice(-5);
        $olderVariances = $variances->slice(-10, 5);
        
        $recentAvg = $recentVariances->avg();
        $olderAvg = $olderVariances->avg();
        
        $trend = 'Stable';
        if (abs($recentAvg - $olderAvg) > 2) {
            $trend = $recentAvg > $olderAvg ? 'Increasing Losses' : 'Decreasing Losses';
        }
        
        $pattern = 'Normal';
        if ($variances->filter(fn($v) => $v < -5)->count() / $variances->count() > 0.7) {
            $pattern = 'Consistent Losses';
        } elseif ($variances->filter(fn($v) => abs($v) > 10)->count() / $variances->count() > 0.5) {
            $pattern = 'High Variability';
        }
        
        return [
            'pattern' => $pattern,
            'trend' => $trend,
            'average_variance' => round($avgVariance, 2),
            'variance_trend' => $this->calculateVarianceTrend($variances),
            'total_checks' => $historicalChecks->count(),
            'critical_checks' => $historicalChecks->where('variance_status', 'Critical')->count()
        ];
    }

    private function calculateVarianceTrend($variances): string
    {
        if ($variances->count() < 3) {
            return 'Unknown';
        }
        
        $first = $variances->slice(0, 3)->avg();
        $last = $variances->slice(-3)->avg();
        
        $change = $last - $first;
        
        if (abs($change) < 1) {
            return 'Stable';
        } elseif ($change > 1) {
            return 'Worsening';
        } else {
            return 'Improving';
        }
    }

    // Quality Control Methods
    public function validateCheck(): array
    {
        $issues = [];
        
        // Basic validation
        if ($this->physical_level < 0) {
            $issues[] = "Physical level cannot be negative";
        }
        
        if ($this->system_level < 0) {
            $issues[] = "System level cannot be negative";
        }
        
        // Checkable validation
        if ($this->checkable) {
            if ($this->physical_level > $this->checkable->capacity) {
                $issues[] = "Physical level exceeds container capacity ({$this->checkable->capacity}L)";
            }
        }
        
        // Variance validation
        $variance = abs($this->getVariance());
        $percentage = abs($this->getVariancePercentage());
        
        if ($variance > 500) {
            $issues[] = "Extremely large variance - verify measurements";
        }
        
        if ($percentage > 50) {
            $issues[] = "Variance exceeds 50% - check measurement accuracy";
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $this->getValidationWarnings()
        ];
    }

    private function getValidationWarnings(): array
    {
        $warnings = [];
        
        $variance = $this->getVariance();
        $percentage = $this->getVariancePercentage();
        
        if (abs($percentage) > 10) {
            $warnings[] = "High variance percentage - consider rechecking";
        }
        
        if ($this->check_method === 'Visual' && abs($variance) > 50) {
            $warnings[] = "Visual measurement with large variance - use more precise method";
        }
        
        if ($this->isPossibleMeasurementError()) {
            $warnings[] = "Possible measurement error - verify equipment calibration";
        }
        
        return $warnings;
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return $this->check_number ?: "Check #{$this->id}";
    }

    public function getCheckableNameAttribute(): string
    {
        return $this->checkable ? $this->checkable->display_name : 'Unknown';
    }

    public function getVarianceAmountAttribute(): float
    {
        return $this->getVariance();
    }

    public function getVariancePercentAttribute(): float
    {
        return $this->getVariancePercentage();
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->variance_status) {
            'Normal' => 'success',
            'Minor' => 'primary',
            'Warning' => 'warning',
            'Critical' => 'danger',
            default => 'secondary'
        };
    }

    public function getAccuracyColorAttribute(): string
    {
        return match ($this->getCheckAccuracy()) {
            'Excellent' => 'success',
            'Very Good' => 'success',
            'Good' => 'primary',
            'Fair' => 'warning',
            'Poor' => 'danger',
            default => 'secondary'
        };
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->check_date->format('d/m/Y') . ' ' . $this->check_time->format('H:i');
    }

    public function getVarianceDescriptionAttribute(): string
    {
        $variance = $this->getVariance();
        $percentage = $this->getVariancePercentage();
        
        if ($variance > 0) {
            return "Surplus: +{$variance}L ({$percentage}%)";
        } elseif ($variance < 0) {
            return "Deficit: {$variance}L ({$percentage}%)";
        } else {
            return "Exact match";
        }
    }

    public function getAttentionRequiredAttribute(): bool
    {
        return $this->requiresAttention();
    }

    public function getAgeInHoursAttribute(): float
    {
        $checkDateTime = Carbon::parse($this->check_datetime);
        return $checkDateTime->diffInHours(now(), false);
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->age_in_hours <= 24;
    }

    public function getCanAdjustAttribute(): bool
    {
        return $this->canBeAdjusted();
    }

    public function getCheckSummaryAttribute(): array
    {
        return [
            'check_number' => $this->check_number,
            'checkable' => $this->checkable_name,
            'checker' => $this->checker_name,
            'method' => $this->check_method,
            'datetime' => $this->formatted_date_time,
            'system_level' => $this->system_level,
            'physical_level' => $this->physical_level,
            'variance' => $this->variance_amount,
            'variance_percentage' => $this->variance_percent,
            'status' => $this->variance_status,
            'accuracy' => $this->getCheckAccuracy(),
            'action_required' => $this->getRecommendedAction(),
            'adjusted' => $this->system_adjusted
        ];
    }

    // Check if this is a follow-up check
    public function isFollowUpCheck(): bool
    {
        return static::where('checkable_type', $this->checkable_type)
                    ->where('checkable_id', $this->checkable_id)
                    ->where('check_date', '>', $this->check_date->subDays(7))
                    ->where('check_date', '<', $this->check_date)
                    ->whereIn('variance_status', ['Warning', 'Critical'])
                    ->exists();
    }

    public function getNextRecommendedCheckDate(): Carbon
    {
        $baseInterval = 7; // Default 7 days
        
        // Adjust interval based on variance status
        $interval = match ($this->variance_status) {
            'Critical' => 1, // Daily
            'Warning' => 3,  // Every 3 days
            'Minor' => 7,    // Weekly
            'Normal' => 14,  // Bi-weekly
            default => 7
        };
        
        return $this->check_date->addDays($interval);
    }
}