<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    // Helper Methods
    public function generateTransactionNumber(): string
    {
        $date = $this->transaction_datetime->format('Ymd');
        $sequence = static::whereDate('transaction_datetime', $this->transaction_datetime->toDateString())->count() + 1;
        
        return "TXN-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getHourMeterDiff(): float
    {
        return $this->current_hour_meter - $this->previous_hour_meter;
    }

    public function getOdometerDiff(): float
    {
        return $this->current_odometer - $this->previous_odometer;
    }

    public function calculateEfficiency(): void
    {
        $hourDiff = $this->getHourMeterDiff();
        $kmDiff = $this->getOdometerDiff();
        
        // Calculate efficiency per hour
        if ($hourDiff > 0) {
            $this->fuel_efficiency_per_hour = round($this->fuel_amount / $hourDiff, 4);
        }
        
        // Calculate efficiency per km
        if ($kmDiff > 0) {
            $this->fuel_efficiency_per_km = round($this->fuel_amount / $kmDiff, 4);
        }
        
        // Calculate combined efficiency (weighted average)
        $this->combined_efficiency = $this->calculateCombinedEfficiency();
        $this->calculated_at = now();
        
        $this->save();
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

    // Validation Methods
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
}