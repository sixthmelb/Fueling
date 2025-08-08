<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class FuelTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_number',
        'fuel_storage_id',
        'fuel_truck_id',
        'daily_session_id',
        'transferred_amount',
        'storage_level_before',
        'storage_level_after',
        'truck_level_before',
        'truck_level_after',
        'transfer_datetime',
        'operator_name',
        'notes',
    ];

    protected $casts = [
        'transferred_amount' => 'decimal:2',
        'storage_level_before' => 'decimal:2',
        'storage_level_after' => 'decimal:2',
        'truck_level_before' => 'decimal:2',
        'truck_level_after' => 'decimal:2',
        'transfer_datetime' => 'datetime',
    ];

    // Relationships
    public function fuelStorage(): BelongsTo
    {
        return $this->belongsTo(FuelStorage::class);
    }

    public function fuelTruck(): BelongsTo
    {
        return $this->belongsTo(FuelTruck::class);
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('transfer_datetime', today());
    }

    public function scopeByStorage($query, $storageId)
    {
        return $query->where('fuel_storage_id', $storageId);
    }

    public function scopeByTruck($query, $truckId)
    {
        return $query->where('fuel_truck_id', $truckId);
    }

    public function scopeBySession($query, $sessionId)
    {
        return $query->where('daily_session_id', $sessionId);
    }

    public function scopeByOperator($query, $operatorName)
    {
        return $query->where('operator_name', 'like', "%{$operatorName}%");
    }

    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('transfer_datetime', [$fromDate, $toDate]);
    }

    public function scopeSuccessful($query)
    {
        return $query->where(function ($q) {
            $q->whereRaw('ABS(storage_level_after - (storage_level_before - transferred_amount)) <= 0.1')
              ->whereRaw('ABS(truck_level_after - (truck_level_before + transferred_amount)) <= 0.1');
        });
    }

    // Helper Methods
    public function generateTransferNumber(): string
    {
        $date = $this->transfer_datetime->format('Ymd');
        $sequence = static::whereDate('transfer_datetime', $this->transfer_datetime->toDateString())->count() + 1;
        
        return "TRF-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getStorageLevelChange(): float
    {
        return $this->storage_level_after - $this->storage_level_before;
    }

    public function getTruckLevelChange(): float
    {
        return $this->truck_level_after - $this->truck_level_before;
    }

    // IMPROVED: Validation Methods with comprehensive checks
    public function validateTransfer(): array
    {
        $issues = [];
        
        // Basic validation
        if ($this->transferred_amount <= 0) {
            $issues[] = "Transfer amount must be greater than 0";
        }
        
        // Storage validation
        if ($this->fuelStorage) {
            $storageValidation = $this->fuelStorage->validateTransferOut($this->transferred_amount);
            if (!$storageValidation['valid']) {
                $issues = array_merge($issues, $storageValidation['issues']);
            }
        } else {
            $issues[] = "Invalid fuel storage";
        }
        
        // Truck validation
        if ($this->fuelTruck) {
            $truckValidation = $this->fuelTruck->validateTransferIn($this->transferred_amount);
            if (!$truckValidation['valid']) {
                $issues = array_merge($issues, $truckValidation['issues']);
            }
        } else {
            $issues[] = "Invalid fuel truck";
        }
        
        // Fuel type compatibility
        if ($this->fuelStorage && $this->fuelTruck) {
            if ($this->fuelStorage->fuel_type !== $this->fuelTruck->fuel_type) {
                $issues[] = "Fuel type mismatch: Storage ({$this->fuelStorage->fuel_type}) vs Truck ({$this->fuelTruck->fuel_type})";
            }
        }
        
        // Level consistency validation (if levels are set)
        if ($this->storage_level_before !== null && $this->fuelStorage) {
            $levelDiff = abs($this->storage_level_before - $this->fuelStorage->current_level);
            if ($levelDiff > 0.1) {
                $issues[] = "Storage level mismatch: Expected {$this->storage_level_before}L, Current {$this->fuelStorage->current_level}L";
            }
        }
        
        if ($this->truck_level_before !== null && $this->fuelTruck) {
            $levelDiff = abs($this->truck_level_before - $this->fuelTruck->current_level);
            if ($levelDiff > 0.1) {
                $issues[] = "Truck level mismatch: Expected {$this->truck_level_before}L, Current {$this->fuelTruck->current_level}L";
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'storage_available' => $this->fuelStorage?->current_level,
            'truck_capacity' => $this->fuelTruck?->getRemainingCapacity(),
            'transfer_amount' => $this->transferred_amount
        ];
    }

    public function isValidTransfer(): bool
    {
        $validation = $this->validateTransfer();
        return $validation['valid'];
    }

    // IMPROVED: Transfer efficiency calculation with detailed analysis
    public function getTransferEfficiency(): float
    {
        // Calculate efficiency based on expected vs actual levels
        $expectedStorageLevel = $this->storage_level_before - $this->transferred_amount;
        $expectedTruckLevel = $this->truck_level_before + $this->transferred_amount;
        
        $storageVariance = abs($this->storage_level_after - $expectedStorageLevel);
        $truckVariance = abs($this->truck_level_after - $expectedTruckLevel);
        
        $totalVariance = $storageVariance + $truckVariance;
        
        if ($this->transferred_amount == 0) {
            return 0;
        }
        
        $efficiency = max(0, 100 - (($totalVariance / $this->transferred_amount) * 100));
        
        return round($efficiency, 2);
    }

    public function getDetailedEfficiencyAnalysis(): array
    {
        $expectedStorageAfter = $this->storage_level_before - $this->transferred_amount;
        $expectedTruckAfter = $this->truck_level_before + $this->transferred_amount;
        
        $storageVariance = $this->storage_level_after - $expectedStorageAfter;
        $truckVariance = $this->truck_level_after - $expectedTruckAfter;
        
        return [
            'transfer_amount' => $this->transferred_amount,
            'storage' => [
                'before' => $this->storage_level_before,
                'after' => $this->storage_level_after,
                'expected_after' => $expectedStorageAfter,
                'variance' => $storageVariance,
                'variance_percentage' => $this->transferred_amount > 0 
                    ? round(($storageVariance / $this->transferred_amount) * 100, 2) 
                    : 0
            ],
            'truck' => [
                'before' => $this->truck_level_before,
                'after' => $this->truck_level_after,
                'expected_after' => $expectedTruckAfter,
                'variance' => $truckVariance,
                'variance_percentage' => $this->transferred_amount > 0 
                    ? round(($truckVariance / $this->transferred_amount) * 100, 2) 
                    : 0
            ],
            'overall' => [
                'total_variance' => abs($storageVariance) + abs($truckVariance),
                'efficiency' => $this->getTransferEfficiency(),
                'status' => $this->getEfficiencyStatus()
            ]
        ];
    }

    public function getEfficiencyStatus(): string
    {
        $efficiency = $this->getTransferEfficiency();
        
        return match (true) {
            $efficiency >= 99 => 'Excellent',
            $efficiency >= 95 => 'Good',
            $efficiency >= 90 => 'Fair',
            $efficiency >= 80 => 'Poor',
            default => 'Very Poor'
        };
    }

    // Loss/Gain Analysis
    public function getFuelLossGain(): array
    {
        $storageChange = $this->getStorageLevelChange();
        $truckChange = $this->getTruckLevelChange();
        
        $expectedStorageChange = -$this->transferred_amount;
        $expectedTruckChange = $this->transferred_amount;
        
        $storageLossGain = $storageChange - $expectedStorageChange;
        $truckLossGain = $truckChange - $expectedTruckChange;
        
        $totalLossGain = $storageLossGain + $truckLossGain;
        
        return [
            'storage_loss_gain' => round($storageLossGain, 2),
            'truck_loss_gain' => round($truckLossGain, 2),
            'total_loss_gain' => round($totalLossGain, 2),
            'percentage' => $this->transferred_amount > 0 
                ? round(($totalLossGain / $this->transferred_amount) * 100, 2) 
                : 0,
            'status' => $this->getLossGainStatus($totalLossGain)
        ];
    }

    private function getLossGainStatus(float $lossGain): string
    {
        $absLossGain = abs($lossGain);
        
        if ($absLossGain <= 0.1) {
            return 'Perfect';
        } elseif ($absLossGain <= 1) {
            return 'Excellent';
        } elseif ($absLossGain <= 5) {
            return 'Good';
        } elseif ($absLossGain <= 10) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }

    // Quality Assurance Methods
    public function hasSignificantVariance(): bool
    {
        $analysis = $this->getDetailedEfficiencyAnalysis();
        $storageVariance = abs($analysis['storage']['variance']);
        $truckVariance = abs($analysis['truck']['variance']);
        
        return $storageVariance > 5 || $truckVariance > 5;
    }

    public function requiresInvestigation(): bool
    {
        return $this->getTransferEfficiency() < 90 || $this->hasSignificantVariance();
    }

    public function getQualityScore(): float
    {
        $efficiency = $this->getTransferEfficiency();
        $lossGain = $this->getFuelLossGain();
        $validation = $this->validateTransfer();
        
        $score = $efficiency;
        
        // Penalty for validation issues
        if (!$validation['valid']) {
            $score -= count($validation['issues']) * 5;
        }
        
        // Penalty for significant loss/gain
        if (abs($lossGain['percentage']) > 2) {
            $score -= abs($lossGain['percentage']);
        }
        
        return max(0, min(100, round($score, 2)));
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return $this->transfer_number ?: "Transfer #{$this->id}";
    }

    public function getTransferSummaryAttribute(): string
    {
        return "{$this->fuelStorage->storage_code} → {$this->fuelTruck->truck_code} ({$this->transferred_amount}L)";
    }

    public function getEfficiencyStatusAttribute(): string
    {
        return $this->getEfficiencyStatus();
    }

    public function getEfficiencyColorAttribute(): string
    {
        return match ($this->efficiency_status) {
            'Excellent' => 'success',
            'Good' => 'primary',
            'Fair' => 'warning',
            'Poor' => 'danger',
            'Very Poor' => 'danger',
            default => 'secondary'
        };
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->transfer_datetime->format('d/m/Y H:i');
    }

    public function getTransferDetailsAttribute(): array
    {
        return [
            'transfer_number' => $this->transfer_number,
            'from_storage' => $this->fuelStorage->display_name,
            'to_truck' => $this->fuelTruck->display_name,
            'amount' => $this->transferred_amount,
            'operator' => $this->operator_name,
            'datetime' => $this->formatted_date_time,
            'efficiency' => $this->getTransferEfficiency(),
            'status' => $this->efficiency_status,
            'quality_score' => $this->getQualityScore()
        ];
    }

    public function getStorageInfoAttribute(): array
    {
        return [
            'code' => $this->fuelStorage->storage_code,
            'name' => $this->fuelStorage->storage_name,
            'before' => $this->storage_level_before,
            'after' => $this->storage_level_after,
            'change' => $this->getStorageLevelChange(),
            'expected_change' => -$this->transferred_amount
        ];
    }

    public function getTruckInfoAttribute(): array
    {
        return [
            'code' => $this->fuelTruck->truck_code,
            'name' => $this->fuelTruck->truck_name,
            'before' => $this->truck_level_before,
            'after' => $this->truck_level_after,
            'change' => $this->getTruckLevelChange(),
            'expected_change' => $this->transferred_amount
        ];
    }

    public function getCanBeModifiedAttribute(): bool
    {
        // Can be modified if session is still active
        return $this->dailySession?->canBeModified() ?? true;
    }

    public function getAgeInHoursAttribute(): float
    {
        return $this->transfer_datetime->diffInHours(now(), false);
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->age_in_hours <= 24;
    }
}