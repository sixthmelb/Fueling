<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PhysicalStockCheck extends Model
{
    use HasFactory;

     protected $fillable = [
        'check_number',
        'checkable_type',
        'checkable_id',
        'check_date',
        'check_time',
        'check_datetime',         // Tambahkan ini jika pakai solusi 2
        'system_level',
        'physical_level',
        'variance',              // Tambahkan ini jika pakai solusi 2
        'variance_percentage',   // Tambahkan ini jika pakai solusi 2
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
        return round($this->physical_level - $this->system_level, 2);
    }

    public function getVariancePercentage(): float
    {
        if ($this->system_level == 0) {
            return 0;
        }
        
        return round(($this->getVariance() / $this->system_level) * 100, 4);
    }

    public function calculateVarianceStatus(): string
    {
        $variancePercentage = abs($this->getVariancePercentage());
        
        if ($variancePercentage <= 2) {
            return 'Normal';
        } elseif ($variancePercentage <= 5) {
            return 'Warning';
        } else {
            return 'Critical';
        }
    }

    public function updateVarianceStatus(): void
    {
        $this->variance_status = $this->calculateVarianceStatus();
        $this->save();
    }

    public function adjustSystem(string $reason = null): bool
    {
        if ($this->system_adjusted) {
            return false; // Already adjusted
        }

        $adjustmentAmount = $this->getVariance();
        
        // Update the checkable item's level
        if ($this->checkable && method_exists($this->checkable, 'updateLevel')) {
            $success = $this->checkable->updateLevel($this->physical_level);
            
            if ($success) {
                $this->update([
                    'system_adjusted' => true,
                    'adjustment_amount' => $adjustmentAmount,
                    'corrective_action' => $reason ? ($this->corrective_action . "\n" . $reason) : 
                                          ($this->corrective_action . "\nSystem adjusted to physical level"),
                ]);
                
                return true;
            }
        }
        
        return false;
    }

    public function requiresAttention(): bool
    {
        return !$this->system_adjusted && in_array($this->variance_status, ['Warning', 'Critical']);
    }

    // Analysis Methods
    public function getCheckAccuracy(): string
    {
        $variancePercentage = abs($this->getVariancePercentage());
        
        return match (true) {
            $variancePercentage <= 1 => 'Excellent',
            $variancePercentage <= 2 => 'Good',
            $variancePercentage <= 5 => 'Fair',
            default => 'Poor'
        };
    }

    public function isPossibleTheft(): bool
    {
        // Significant negative variance might indicate theft
        return $this->getVariance() < -10 && $this->getVariancePercentage() < -5;
    }

    public function isPossibleLeakage(): bool
    {
        // Consistent negative variances might indicate leakage
        $recentChecks = static::where('checkable_type', $this->checkable_type)
                             ->where('checkable_id', $this->checkable_id)
                             ->where('check_date', '>=', now()->subDays(7))
                             ->where('id', '!=', $this->id)
                             ->get();
        
        if ($recentChecks->count() < 2) {
            return false;
        }
        
        $negativeCount = $recentChecks->where('variance', '<', -5)->count();
        return ($negativeCount / $recentChecks->count()) >= 0.7; // 70% of recent checks are negative
    }

    public function getRecommendedAction(): string
    {
        if ($this->system_adjusted) {
            return 'Completed';
        }
        
        $variance = $this->getVariance();
        $variancePercentage = $this->getVariancePercentage();
        
        if ($this->isPossibleTheft()) {
            return 'Investigate possible theft';
        }
        
        if ($this->isPossibleLeakage()) {
            return 'Check for leakage';
        }
        
        if (abs($variancePercentage) > 5) {
            return 'Adjust system level';
        }
        
        if (abs($variancePercentage) > 2) {
            return 'Monitor closely';
        }
        
        return 'No action required';
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

    /**
     * MYSQL FIX: Accessor with fallback
     */
    public function getVarianceAttribute($value): float
    {
        if ($value !== null) {
            return $value;
        }
        return $this->physical_level - $this->system_level;
    }

    /**
     * MYSQL FIX: Accessor with fallback
     */
    public function getVariancePercentageAttribute($value): float
    {
        if ($value !== null) {
            return $value;
        }
        
        if ($this->system_level == 0) {
            return 0;
        }
        return ($this->variance / $this->system_level) * 100;
    }

    /**
     * MYSQL FIX: Accessor with fallback
     */
    public function getCheckDatetimeAttribute($value): string
    {
        if ($value !== null) {
            return $value;
        }
        return $this->check_date . ' ' . $this->check_time;
    }

    /**
     * Keep existing getVariance() method for backward compatibility
     */
    public function getVariance(): float
    {
        return $this->variance;
    }

    /**
     * Keep existing getVariancePercentage() method for backward compatibility
     */
    public function getVariancePercentage(): float
    {
        return $this->variance_percentage;
    }
    

    public function getStatusColorAttribute(): string
    {
        return match ($this->variance_status) {
            'Normal' => 'success',
            'Warning' => 'warning',
            'Critical' => 'danger',
            default => 'secondary'
        };
    }

    public function getAccuracyColorAttribute(): string
    {
        return match ($this->getCheckAccuracy()) {
            'Excellent' => 'success',
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

    // Check if this is a follow-up check
    public function isFollowUpCheck(): bool
    {
        return static::where('checkable_type', $this->checkable_type)
                    ->where('checkable_id', $this->checkable_id)
                    ->where('check_date', '>', $this->check_date->subDays(3))
                    ->where('check_date', '<', $this->check_date)
                    ->whereIn('variance_status', ['Warning', 'Critical'])
                    ->exists();
    }
}