<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FuelTruck extends Model
{
    use HasFactory;

    protected $fillable = [
        'truck_code',
        'truck_name',
        'capacity',
        'current_level',
        'license_plate',
        'driver_name',
        'brand',
        'model',
        'manufacture_year',
        'fuel_type',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'decimal:2',
        'current_level' => 'decimal:2',
        'manufacture_year' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function fuelTransfers(): HasMany
    {
        return $this->hasMany(FuelTransfer::class);
    }

    public function fuelDistributions(): MorphMany
    {
        return $this->morphMany(FuelTransaction::class, 'fuel_source');
    }

    public function physicalStockChecks(): MorphMany
    {
        return $this->morphMany(PhysicalStockCheck::class, 'checkable');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByFuelType($query, $fuelType)
    {
        return $query->where('fuel_type', $fuelType);
    }

    public function scopeByDriver($query, $driverName)
    {
        return $query->where('driver_name', 'like', "%{$driverName}%");
    }

    // Helper Methods
    public function todayTransfers()
    {
        return $this->fuelTransfers()->whereDate('transfer_datetime', today());
    }

    public function todayDistributions()
    {
        return $this->fuelDistributions()->whereDate('transaction_datetime', today());
    }

    public function todayTotalReceived(): float
    {
        return $this->todayTransfers()->sum('transferred_amount') ?? 0;
    }

    public function todayTotalDistributed(): float
    {
        return $this->todayDistributions()->sum('fuel_amount') ?? 0;
    }

    // Stock Management
    public function addFuel(float $amount, string $notes = null): bool
    {
        if ($this->current_level + $amount > $this->capacity) {
            return false; // Cannot exceed capacity
        }

        $this->increment('current_level', $amount);
        return true;
    }

    public function removeFuel(float $amount, string $notes = null): bool
    {
        if ($this->current_level < $amount) {
            return false; // Insufficient fuel
        }

        $this->decrement('current_level', $amount);
        return true;
    }

    public function updateLevel(float $newLevel): bool
    {
        if ($newLevel < 0 || $newLevel > $this->capacity) {
            return false;
        }

        $this->update(['current_level' => $newLevel]);
        return true;
    }

    // Analysis Methods
    public function getCapacityUsagePercentage(): float
    {
        if ($this->capacity == 0) return 0;
        return round(($this->current_level / $this->capacity) * 100, 2);
    }

    public function getRemainingCapacity(): float
    {
        return $this->capacity - $this->current_level;
    }

    public function isEmpty(): bool
    {
        return $this->current_level <= 0;
    }

    public function isFull(): bool
    {
        return $this->current_level >= $this->capacity;
    }

    public function canReceive(float $amount): bool
    {
        return ($this->current_level + $amount) <= $this->capacity;
    }

    public function canDispense(float $amount): bool
    {
        return $this->current_level >= $amount;
    }

    public function getLatestTransfer()
    {
        return $this->fuelTransfers()->latest('transfer_datetime')->first();
    }

    public function getLatestDistribution()
    {
        return $this->fuelDistributions()->latest('transaction_datetime')->first();
    }

    public function getLatestStockCheck()
    {
        return $this->physicalStockChecks()->latest('check_datetime')->first();
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return "{$this->truck_code} - {$this->truck_name}";
    }

    public function getFullInfoAttribute(): string
    {
        $info = $this->display_name;
        if ($this->driver_name) {
            $info .= " (Driver: {$this->driver_name})";
        }
        if ($this->license_plate) {
            $info .= " [{$this->license_plate}]";
        }
        return $info;
    }

    public function getStatusAttribute(): string
    {
        if ($this->isEmpty()) {
            return 'Empty';
        } elseif ($this->isFull()) {
            return 'Full';
        } else {
            $percentage = $this->getCapacityUsagePercentage();
            if ($percentage >= 75) {
                return 'High';
            } elseif ($percentage >= 25) {
                return 'Medium';
            } else {
                return 'Low';
            }
        }
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Empty' => 'danger',
            'Full' => 'success',
            'High' => 'success',
            'Medium' => 'warning',
            'Low' => 'danger',
            default => 'secondary'
        };
    }

    public function getAgeAttribute(): ?int
    {
        return $this->manufacture_year ? now()->year - $this->manufacture_year : null;
    }
}