<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_code',
        'unit_name',
        'unit_type_id',
        'current_hour_meter',
        'current_odometer',
        'brand',
        'model',
        'manufacture_year',
        'fuel_tank_capacity',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'current_hour_meter' => 'decimal:2',
        'current_odometer' => 'decimal:2',
        'fuel_tank_capacity' => 'decimal:2',
        'manufacture_year' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    public function unitConsumptionSummaries(): HasMany
    {
        return $this->hasMany(UnitConsumptionSummary::class);
    }

    // Latest relationships
    public function latestFuelTransaction(): HasOne
    {
        return $this->hasOne(FuelTransaction::class)->latestOfMany();
    }

    public function latestConsumptionSummary(): HasOne
    {
        return $this->hasOne(UnitConsumptionSummary::class)->latestOfMany();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $unitTypeId)
    {
        return $query->where('unit_type_id', $unitTypeId);
    }

    // Helper Methods for Today's Data
    public function todayFuelTransactions()
    {
        return $this->fuelTransactions()->whereDate('transaction_datetime', today());
    }

    public function todayTotalFuelConsumption(): float
    {
        return $this->todayFuelTransactions()->sum('fuel_amount') ?? 0;
    }

    public function todayTransactionsCount(): int
    {
        return $this->todayFuelTransactions()->count();
    }

    // Consumption Analysis
    public function getAverageConsumptionPerHour(): ?float
    {
        $summary = $this->unitConsumptionSummaries()
            ->where('summary_date', '>=', now()->subDays(30))
            ->avg('avg_fuel_per_hour');
        
        return $summary ? round($summary, 4) : null;
    }

    public function getAverageConsumptionPerKm(): ?float
    {
        $summary = $this->unitConsumptionSummaries()
            ->where('summary_date', '>=', now()->subDays(30))
            ->avg('avg_fuel_per_km');
        
        return $summary ? round($summary, 4) : null;
    }

    // Update Hour Meter and Odometer
    public function updateMeters(float $newHourMeter, float $newOdometer): void
    {
        $this->update([
            'current_hour_meter' => $newHourMeter,
            'current_odometer' => $newOdometer,
        ]);
    }

    // Get Current Consumption Rate
    public function getCurrentConsumptionRate($workCondition = 'Normal')
    {
        return $this->unitType->getCurrentConsumptionRate($workCondition);
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return "{$this->unit_code} - {$this->unit_name}";
    }

    public function getFullSpecAttribute(): string
    {
        $spec = $this->display_name;
        if ($this->brand && $this->model) {
            $spec .= " ({$this->brand} {$this->model})";
        }
        return $spec;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->manufacture_year ? now()->year - $this->manufacture_year : null;
    }
}