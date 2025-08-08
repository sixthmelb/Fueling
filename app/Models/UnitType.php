<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitType extends Model
{
    use HasFactory;

    protected $fillable = [
        'type_code',
        'type_name',
        'description',
        'default_consumption_per_hour',
        'default_consumption_per_km',
        'is_active',
    ];

    protected $casts = [
        'default_consumption_per_hour' => 'decimal:2',
        'default_consumption_per_km' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function activeUnits(): HasMany
    {
        return $this->hasMany(Unit::class)->where('is_active', true);
    }

    public function fuelConsumptionRates(): HasMany
    {
        return $this->hasMany(FuelConsumptionRate::class);
    }

    public function activeFuelConsumptionRates(): HasMany
    {
        return $this->hasMany(FuelConsumptionRate::class)->where('is_active', true);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper Methods
    public function getCurrentConsumptionRate($workCondition = 'Normal')
    {
        return $this->activeFuelConsumptionRates()
            ->where('work_condition', $workCondition)
            ->where('effective_from', '<=', now())
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', now());
            })
            ->first();
    }

    public function getTotalUnitsCount(): int
    {
        return $this->units()->count();
    }

    public function getActiveUnitsCount(): int
    {
        return $this->activeUnits()->count();
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return "{$this->type_code} - {$this->type_name}";
    }
}