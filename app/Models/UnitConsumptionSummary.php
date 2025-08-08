<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConsumptionSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'summary_date',
        'shift_id',
        'total_transactions',
        'total_fuel_consumed',
        'total_hour_meter_diff',
        'total_odometer_diff',
        'avg_fuel_per_hour',
        'avg_fuel_per_km',
        'avg_combined_efficiency',
        'min_efficiency_per_hour',
        'max_efficiency_per_hour',
        'min_efficiency_per_km',
        'max_efficiency_per_km',
        'period_type',
        'first_transaction_at',
        'last_transaction_at',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'total_transactions' => 'integer',
        'total_fuel_consumed' => 'decimal:2',
        'total_hour_meter_diff' => 'decimal:2',
        'total_odometer_diff' => 'decimal:2',
        'avg_fuel_per_hour' => 'decimal:4',
        'avg_fuel_per_km' => 'decimal:4',
        'avg_combined_efficiency' => 'decimal:4',
        'min_efficiency_per_hour' => 'decimal:4',
        'max_efficiency_per_hour' => 'decimal:4',
        'min_efficiency_per_km' => 'decimal:4',
        'max_efficiency_per_km' => 'decimal:4',
        'first_transaction_at' => 'datetime',
        'last_transaction_at' => 'datetime',
    ];

    // Relationships
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    // Scopes
    public function scopeDaily($query)
    {
        return $query->where('period_type', 'Daily');
    }

    public function scopeShift($query)
    {
        return $query->where('period_type', 'Shift');
    }

    public function scopeByUnit($query, $unitId)
    {
        return $query->where('unit_id', $unitId);
    }

    public function scopeByShift($query, $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('summary_date', [$fromDate, $toDate]);
    }

    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('summary_date', now()->month)
                    ->whereYear('summary_date', now()->year);
    }

    public function scopeLastMonth($query)
    {
        $lastMonth = now()->subMonth();
        return $query->whereMonth('summary_date', $lastMonth->month)
                    ->whereYear('summary_date', $lastMonth->year);
    }

    // Helper Methods
    public static function generateForUnit(Unit $unit, string $date, ?Shift $shift = null): self
    {
        $query = $unit->fuelTransactions()
                     ->whereDate('transaction_datetime', $date);
        
        if ($shift) {
            $query->whereHas('dailySession', function ($q) use ($shift) {
                $q->where('shift_id', $shift->id);
            });
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            return new self(); // Return empty summary
        }

        $summary = new self([
            'unit_id' => $unit->id,
            'summary_date' => $date,
            'shift_id' => $shift?->id,
            'period_type' => $shift ? 'Shift' : 'Daily',
            'total_transactions' => $transactions->count(),
            'total_fuel_consumed' => $transactions->sum('fuel_amount'),
            'total_hour_meter_diff' => $transactions->sum('hour_meter_diff'),
            'total_odometer_diff' => $transactions->sum('odometer_diff'),
            'first_transaction_at' => $transactions->min('transaction_datetime'),
            'last_transaction_at' => $transactions->max('transaction_datetime'),
        ]);

        // Calculate averages and min/max
        $efficiencies = $transactions->where('fuel_efficiency_per_hour', '>', 0);
        if ($efficiencies->isNotEmpty()) {
            $summary->avg_fuel_per_hour = $efficiencies->avg('fuel_efficiency_per_hour');
            $summary->min_efficiency_per_hour = $efficiencies->min('fuel_efficiency_per_hour');
            $summary->max_efficiency_per_hour = $efficiencies->max('fuel_efficiency_per_hour');
        }

        $kmEfficiencies = $transactions->where('fuel_efficiency_per_km', '>', 0);
        if ($kmEfficiencies->isNotEmpty()) {
            $summary->avg_fuel_per_km = $kmEfficiencies->avg('fuel_efficiency_per_km');
            $summary->min_efficiency_per_km = $kmEfficiencies->min('fuel_efficiency_per_km');
            $summary->max_efficiency_per_km = $kmEfficiencies->max('fuel_efficiency_per_km');
        }

        $combinedEfficiencies = $transactions->where('combined_efficiency', '>', 0);
        if ($combinedEfficiencies->isNotEmpty()) {
            $summary->avg_combined_efficiency = $combinedEfficiencies->avg('combined_efficiency');
        }

        return $summary;
    }

    public function updateFromTransactions(): void
    {
        $query = $this->unit->fuelTransactions()
                          ->whereDate('transaction_datetime', $this->summary_date);
        
        if ($this->shift_id) {
            $query->whereHas('dailySession', function ($q) {
                $q->where('shift_id', $this->shift_id);
            });
        }

        $transactions = $query->get();

        if ($transactions->isEmpty()) {
            $this->delete();
            return;
        }

        $this->update([
            'total_transactions' => $transactions->count(),
            'total_fuel_consumed' => $transactions->sum('fuel_amount'),
            'total_hour_meter_diff' => $transactions->sum('hour_meter_diff'),
            'total_odometer_diff' => $transactions->sum('odometer_diff'),
            'first_transaction_at' => $transactions->min('transaction_datetime'),
            'last_transaction_at' => $transactions->max('transaction_datetime'),
        ]);

        // Recalculate efficiency metrics
        $this->recalculateEfficiencies($transactions);
    }

    private function recalculateEfficiencies($transactions): void
    {
        // Hour-based efficiency
        $efficiencies = $transactions->where('fuel_efficiency_per_hour', '>', 0);
        if ($efficiencies->isNotEmpty()) {
            $this->avg_fuel_per_hour = round($efficiencies->avg('fuel_efficiency_per_hour'), 4);
            $this->min_efficiency_per_hour = $efficiencies->min('fuel_efficiency_per_hour');
            $this->max_efficiency_per_hour = $efficiencies->max('fuel_efficiency_per_hour');
        }

        // KM-based efficiency
        $kmEfficiencies = $transactions->where('fuel_efficiency_per_km', '>', 0);
        if ($kmEfficiencies->isNotEmpty()) {
            $this->avg_fuel_per_km = round($kmEfficiencies->avg('fuel_efficiency_per_km'), 4);
            $this->min_efficiency_per_km = $kmEfficiencies->min('fuel_efficiency_per_km');
            $this->max_efficiency_per_km = $kmEfficiencies->max('fuel_efficiency_per_km');
        }

        // Combined efficiency
        $combinedEfficiencies = $transactions->where('combined_efficiency', '>', 0);
        if ($combinedEfficiencies->isNotEmpty()) {
            $this->avg_combined_efficiency = round($combinedEfficiencies->avg('combined_efficiency'), 4);
        }

        $this->save();
    }

    // Analysis Methods
    public function getEfficiencyTrend(): ?string
    {
        $previousSummary = static::where('unit_id', $this->unit_id)
                                ->where('period_type', $this->period_type)
                                ->where('summary_date', '<', $this->summary_date)
                                ->orderBy('summary_date', 'desc')
                                ->first();

        if (!$previousSummary || !$this->avg_combined_efficiency || !$previousSummary->avg_combined_efficiency) {
            return null;
        }

        $change = (($this->avg_combined_efficiency - $previousSummary->avg_combined_efficiency) / $previousSummary->avg_combined_efficiency) * 100;

        if ($change > 5) {
            return 'Improving';
        } elseif ($change < -5) {
            return 'Declining';
        } else {
            return 'Stable';
        }
    }

    public function getEfficiencyVariance(): float
    {
        if (!$this->min_efficiency_per_hour || !$this->max_efficiency_per_hour || !$this->avg_fuel_per_hour) {
            return 0;
        }

        $range = $this->max_efficiency_per_hour - $this->min_efficiency_per_hour;
        return round(($range / $this->avg_fuel_per_hour) * 100, 2);
    }

    public function isEfficiencyConsistent(): bool
    {
        return $this->getEfficiencyVariance() <= 20; // Less than 20% variance
    }

    public function getWorkingHours(): ?float
    {
        if (!$this->first_transaction_at || !$this->last_transaction_at) {
            return null;
        }

        return round($this->first_transaction_at->diffInMinutes($this->last_transaction_at) / 60, 2);
    }

    public function getAverageTransactionSize(): float
    {
        return $this->total_transactions > 0 ? 
               round($this->total_fuel_consumed / $this->total_transactions, 2) : 0;
    }

    // Comparison Methods
    public function compareWithUnitTypeAverage(): array
    {
        $unitTypeAvg = static::join('units', 'units.id', '=', 'unit_consumption_summaries.unit_id')
                            ->where('units.unit_type_id', $this->unit->unit_type_id)
                            ->where('period_type', $this->period_type)
                            ->where('summary_date', '>=', now()->subDays(30))
                            ->avg('avg_combined_efficiency');

        if (!$unitTypeAvg || !$this->avg_combined_efficiency) {
            return ['comparison' => 'No data', 'variance' => null];
        }

        $variance = (($this->avg_combined_efficiency - $unitTypeAvg) / $unitTypeAvg) * 100;
        
        $comparison = match (true) {
            $variance <= -15 => 'Much Better',
            $variance <= -5 => 'Better',
            $variance <= 5 => 'Average',
            $variance <= 15 => 'Below Average',
            default => 'Much Below Average'
        };

        return [
            'comparison' => $comparison,
            'variance' => round($variance, 2),
            'unit_avg' => $this->avg_combined_efficiency,
            'type_avg' => round($unitTypeAvg, 4)
        ];
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        $name = $this->unit->unit_code . ' - ' . $this->summary_date->format('d/m/Y');
        
        if ($this->shift) {
            $name .= ' (' . $this->shift->shift_name . ')';
        }
        
        return $name;
    }

    public function getEfficiencyStatusAttribute(): string
    {
        $comparison = $this->compareWithUnitTypeAverage();
        return $comparison['comparison'];
    }

    public function getEfficiencyColorAttribute(): string
    {
        return match ($this->efficiency_status) {
            'Much Better' => 'success',
            'Better' => 'primary',
            'Average' => 'info',
            'Below Average' => 'warning',
            'Much Below Average' => 'danger',
            default => 'secondary'
        };
    }

    public function getTrendColorAttribute(): string
    {
        return match ($this->getEfficiencyTrend()) {
            'Improving' => 'success',
            'Stable' => 'info',
            'Declining' => 'warning',
            default => 'secondary'
        };
    }

    public function getFormattedPeriodAttribute(): string
    {
        $formatted = $this->summary_date->format('d/m/Y');
        
        if ($this->period_type === 'Shift' && $this->shift) {
            $formatted .= ' - ' . $this->shift->shift_name;
        }
        
        return $formatted;
    }
}