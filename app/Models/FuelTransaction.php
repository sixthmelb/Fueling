<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;

class FuelTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'unit_id',
        'daily_session_id',
        'fuel_source_type',
        'fuel_source_id',
        'previous_hour_meter',
        'current_hour_meter',
        'previous_odometer',
        'current_odometer',
        'fuel_amount',
        'source_level_before',
        'source_level_after',
        'fuel_efficiency_per_hour',
        'fuel_efficiency_per_km',
        'combined_efficiency',
        'transaction_datetime',
        'operator_name',
        'notes',
        'calculated_at',
    ];

    protected $casts = [
        'previous_hour_meter' => 'decimal:2',
        'current_hour_meter' => 'decimal:2',
        'previous_odometer' => 'decimal:2',
        'current_odometer' => 'decimal:2',
        'fuel_amount' => 'decimal:2',
        'source_level_before' => 'decimal:2',
        'source_level_after' => 'decimal:2',
        'fuel_efficiency_per_hour' => 'decimal:4',
        'fuel_efficiency_per_km' => 'decimal:4',
        'combined_efficiency' => 'decimal:4',
        'transaction_datetime' => 'datetime',
        'calculated_at' => 'datetime',
    ];

    // Relationships
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function dailySession(): BelongsTo
    {
        return $this->belongsTo(DailySession::class);
    }

    public function fuelSource(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('transaction_datetime', today());
    }

    public function scopeByUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
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
        return $query->whereBetween('transaction_datetime', [$fromDate, $toDate]);
    }

    public function scopeFromStorage($query)
    {
        return $query->where('fuel_source_type', FuelStorage::class);
    }

    public function scopeFromTruck($query)
    {
        return $query->where('fuel_source_type', FuelTruck::class);
    }

    public function scopeWithEfficiency($query)
    {
        return $query->whereNotNull('fuel_efficiency_per_hour')
                    ->orWhereNotNull('fuel_efficiency_per_km');
    }

    // MYSQL FIX: Accessor methods for computed columns
    public function getHourMeterDiffAttribute(): float
    {
        return $this->current_hour_meter - $this->previous_hour_meter;
    }

    public function getOdometerDiffAttribute(): float
    {
        return $this->current_odometer - $this->previous_odometer;
    }

    // Helper Methods
    public function generateTransactionNumber(): string
    {
        $date = $this->transaction_datetime->format('Ymd');
        $sequence = static::whereDate('transaction_datetime', $this->transaction_datetime->toDateString())->count() + 1;
        
        return "TXN-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getHourMeterDiff(): float
    {
        return $this->hour_meter_diff;
    }

    public function getOdometerDiff(): float
    {
        return $this->odometer_diff;
    }

    // IMPROVED: Efficiency calculation with better error handling
    public function calculateEfficiency(): void
    {
        $hourDiff = $this->getHourMeterDiff();
        $kmDiff = $this->getOdometerDiff();
        
        Log::info('Calculating efficiency for transaction', [
            'transaction_id' => $this->id,
            'unit_code' => $this->unit->unit_code,
            'hour_diff' => $hourDiff,
            'km_diff' => $kmDiff,
            'fuel_amount' => $this->fuel_amount
        ]);
        
        // Calculate efficiency per hour
        if ($hourDiff > 0) {
            $this->fuel_efficiency_per_hour = round($this->fuel_amount / $hourDiff, 4);
        } else {
            $this->fuel_efficiency_per_hour = null;
            Log::warning('Zero hour meter difference in transaction', [
                'transaction_id' => $this->id,
                'unit_code' => $this->unit->unit_code
            ]);
        }
        
        // Calculate efficiency per km
        if ($kmDiff > 0) {
            $this->fuel_efficiency_per_km = round($this->fuel_amount / $kmDiff, 4);
        } else {
            $this->fuel_efficiency_per_km = null;
            Log::warning('Zero odometer difference in transaction', [
                'transaction_id' => $this->id,
                'unit_code' => $this->unit->unit_code
            ]);
        }
        
        // Calculate combined efficiency (weighted average)
        $this->combined_efficiency = $this->calculateCombinedEfficiency();
        $this->calculated_at = now();
        
        $this->save();
        
        Log::info('Efficiency calculated successfully', [
            'transaction_id' => $this->id,
            'efficiency_per_hour' => $this->fuel_efficiency_per_hour,
            'efficiency_per_km' => $this->fuel_efficiency_per_km,
            'combined_efficiency' => $this->combined_efficiency
        ]);
    }

    private function calculateCombinedEfficiency(): ?float
    {
        $hourEff = $this->fuel_efficiency_per_hour;
        $kmEff = $this->fuel_efficiency_per_km;
        
        if (!$hourEff && !$kmEff) {
            return null;
        }
        
        if (!$hourEff) {
            return $kmEff;
        }
        
        if (!$kmEff) {
            return $hourEff;
        }
        
        // Weighted average (70% hour-based, 30% km-based for heavy equipment)
        return round(($hourEff * 0.7) + ($kmEff * 0.3), 4);
    }

    // IMPROVED: Validation Methods with better error reporting
    public function validateTransaction(): array
    {
        $issues = [];
        
        // Validate meter readings
        if ($this->current_hour_meter < $this->previous_hour_meter) {
            $issues[] = "Current hour meter ({$this->current_hour_meter}) cannot be less than previous ({$this->previous_hour_meter})";
        }
        
        if ($this->current_odometer < $this->previous_odometer) {
            $issues[] = "Current odometer ({$this->current_odometer}) cannot be less than previous ({$this->previous_odometer})";
        }
        
        // Validate fuel amount
        if ($this->fuel_amount <= 0) {
            $issues[] = "Fuel amount must be greater than 0";
        }
        
        // Validate fuel source availability
        if ($this->fuelSource) {
            if ($this->fuelSource instanceof FuelStorage) {
                if (!$this->fuelSource->canDispense($this->fuel_amount)) {
                    $issues[] = "Insufficient fuel in storage. Available: {$this->fuelSource->current_level}L, Required: {$this->fuel_amount}L";
                }
            } elseif ($this->fuelSource instanceof FuelTruck) {
                if (!$this->fuelSource->canDispense($this->fuel_amount)) {
                    $issues[] = "Insufficient fuel in truck. Available: {$this->fuelSource->current_level}L, Required: {$this->fuel_amount}L";
                }
            }
        }
        
        // Validate unit capacity if specified
        if ($this->unit->fuel_tank_capacity && $this->fuel_amount > $this->unit->fuel_tank_capacity) {
            $issues[] = "Fuel amount ({$this->fuel_amount}L) exceeds unit tank capacity ({$this->unit->fuel_tank_capacity}L)";
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }

    public function isReasonableConsumption(): bool
    {
        $consumptionRate = $this->unit->getCurrentConsumptionRate();
        
        if (!$consumptionRate) {
            return true; // No rate to compare against
        }
        
        $hourDiff = $this->getHourMeterDiff();
        $kmDiff = $this->getOdometerDiff();
        
        $expectedFuelHour = $hourDiff * $consumptionRate->consumption_per_hour;
        $expectedFuelKm = $kmDiff * $consumptionRate->consumption_per_km;
        $expectedTotal = $expectedFuelHour + $expectedFuelKm;
        
        if ($expectedTotal == 0) {
            return true; // Cannot validate without expected consumption
        }
        
        // Allow 50% variance
        $minExpected = $expectedTotal * 0.5;
        $maxExpected = $expectedTotal * 1.5;
        
        return $this->fuel_amount >= $minExpected && $this->fuel_amount <= $maxExpected;
    }

    public function getConsumptionVariance(): ?float
    {
        $consumptionRate = $this->unit->getCurrentConsumptionRate();
        
        if (!$consumptionRate) {
            return null;
        }
        
        $hourDiff = $this->getHourMeterDiff();
        $kmDiff = $this->getOdometerDiff();
        
        $expectedFuel = ($hourDiff * $consumptionRate->consumption_per_hour) + 
                       ($kmDiff * $consumptionRate->consumption_per_km);
        
        if ($expectedFuel == 0) {
            return null;
        }
        
        return round((($this->fuel_amount - $expectedFuel) / $expectedFuel) * 100, 2);
    }

    // Analysis Methods
    public function getEfficiencyRating(): string
    {
        if (!$this->combined_efficiency) {
            return 'N/A';
        }
        
        // Get unit type average for comparison
        $typeAvg = $this->unit->getAverageConsumptionPerHour();
        
        if (!$typeAvg) {
            return 'Good'; // Default if no comparison data
        }
        
        $variance = (($this->combined_efficiency - $typeAvg) / $typeAvg) * 100;
        
        if ($variance <= -20) {
            return 'Excellent';
        } elseif ($variance <= -10) {
            return 'Good';
        } elseif ($variance <= 10) {
            return 'Average';
        } elseif ($variance <= 20) {
            return 'Below Average';
        } else {
            return 'Poor';
        }
    }

    public function getSourceLevelVariance(): ?float
    {
        if (!$this->source_level_before || !$this->source_level_after) {
            return null;
        }
        
        $expectedAfter = $this->source_level_before - $this->fuel_amount;
        return $this->source_level_after - $expectedAfter;
    }

    public function hasSourceLevelVariance(): bool
    {
        $variance = $this->getSourceLevelVariance();
        return $variance !== null && abs($variance) > 0.1;
    }

    // Efficiency Comparison Methods
    public function compareWithUnitAverage(): array
    {
        $unitAvgHour = $this->unit->getAverageConsumptionPerHour();
        $unitAvgKm = $this->unit->getAverageConsumptionPerKm();
        
        $comparison = [
            'hour_efficiency' => [
                'current' => $this->fuel_efficiency_per_hour,
                'unit_average' => $unitAvgHour,
                'variance' => null,
                'status' => 'N/A'
            ],
            'km_efficiency' => [
                'current' => $this->fuel_efficiency_per_km,
                'unit_average' => $unitAvgKm,
                'variance' => null,
                'status' => 'N/A'
            ]
        ];
        
        if ($this->fuel_efficiency_per_hour && $unitAvgHour) {
            $variance = (($this->fuel_efficiency_per_hour - $unitAvgHour) / $unitAvgHour) * 100;
            $comparison['hour_efficiency']['variance'] = round($variance, 2);
            $comparison['hour_efficiency']['status'] = $this->getVarianceStatus($variance);
        }
        
        if ($this->fuel_efficiency_per_km && $unitAvgKm) {
            $variance = (($this->fuel_efficiency_per_km - $unitAvgKm) / $unitAvgKm) * 100;
            $comparison['km_efficiency']['variance'] = round($variance, 2);
            $comparison['km_efficiency']['status'] = $this->getVarianceStatus($variance);
        }
        
        return $comparison;
    }

    private function getVarianceStatus(float $variance): string
    {
        if ($variance <= -15) {
            return 'Much Better';
        } elseif ($variance <= -5) {
            return 'Better';
        } elseif ($variance <= 5) {
            return 'Similar';
        } elseif ($variance <= 15) {
            return 'Worse';
        } else {
            return 'Much Worse';
        }
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return $this->transaction_number ?: "Transaction #{$this->id}";
    }

    public function getTransactionSummaryAttribute(): string
    {
        return "{$this->unit->unit_code} - {$this->fuel_amount}L";
    }

    public function getFuelSourceNameAttribute(): string
    {
        return $this->fuelSource ? $this->fuelSource->display_name : 'Unknown Source';
    }

    public function getEfficiencyColorAttribute(): string
    {
        return match ($this->getEfficiencyRating()) {
            'Excellent' => 'success',
            'Good' => 'primary',
            'Average' => 'info',
            'Below Average' => 'warning',
            'Poor' => 'danger',
            default => 'secondary'
        };
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->transaction_datetime->format('d/m/Y H:i');
    }

    public function getHasVarianceAttribute(): bool
    {
        $variance = $this->getConsumptionVariance();
        return $variance !== null && abs($variance) > 15;
    }

    public function getWorkingHoursAttribute(): float
    {
        return $this->getHourMeterDiff();
    }

    public function getDistanceTraveledAttribute(): float
    {
        return $this->getOdometerDiff();
    }

    public function getEfficiencySummaryAttribute(): array
    {
        return [
            'per_hour' => $this->fuel_efficiency_per_hour,
            'per_km' => $this->fuel_efficiency_per_km,
            'combined' => $this->combined_efficiency,
            'rating' => $this->getEfficiencyRating(),
            'reasonable' => $this->isReasonableConsumption(),
            'variance' => $this->getConsumptionVariance()
        ];
    }

    public function getTransactionDetailsAttribute(): array
    {
        return [
            'transaction_number' => $this->transaction_number,
            'unit' => $this->unit->unit_code,
            'operator' => $this->operator_name,
            'datetime' => $this->formatted_date_time,
            'fuel_amount' => $this->fuel_amount,
            'fuel_source' => $this->fuel_source_name,
            'hour_meter_diff' => $this->getHourMeterDiff(),
            'odometer_diff' => $this->getOdometerDiff(),
            'efficiency' => $this->efficiency_summary
        ];
    }
}