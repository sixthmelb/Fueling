<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FuelStorage extends Model
{
    use HasFactory;

    protected $fillable = [
        'storage_code',
        'storage_name',
        'capacity',
        'current_level',
        'minimum_level',
        'location',
        'fuel_type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'decimal:2',
        'current_level' => 'decimal:2',
        'minimum_level' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function fuelTransfers(): HasMany
    {
        return $this->hasMany(FuelTransfer::class);
    }

    public function directFuelTransactions(): MorphMany
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

    public function scopeLowLevel($query)
    {
        return $query->whereRaw('current_level <= minimum_level');
    }

    // Helper Methods
    public function todayTransfers()
    {
        return $this->fuelTransfers()->whereDate('transfer_datetime', today());
    }

    public function todayDirectTransactions()
    {
        return $this->directFuelTransactions()->whereDate('transaction_datetime', today());
    }

    public function todayTotalTransferred(): float
    {
        return $this->todayTransfers()->sum('transferred_amount') ?? 0;
    }

    public function todayTotalDirectFuel(): float
    {
        return $this->todayDirectTransactions()->sum('fuel_amount') ?? 0;
    }

    // Stock Management
    public function addFuel(float $amount, string $notes = null): bool
    {
        if ($this->current_level + $amount > $this->capacity) {
            return false; // Cannot exceed capacity
        }

        $this->increment('current_level', $amount);
        
        // Log the addition if needed
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

    public function isLowLevel(): bool
    {
        return $this->current_level <= $this->minimum_level;
    }

    public function getLatestStockCheck()
    {
        return $this->physicalStockChecks()->latest('check_datetime')->first();
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return "{$this->storage_code} - {$this->storage_name}";
    }

    public function getStatusAttribute(): string
    {
        if ($this->isLowLevel()) {
            return 'Low Level';
        }
        
        $percentage = $this->getCapacityUsagePercentage();
        if ($percentage >= 80) {
            return 'High';
        } elseif ($percentage >= 50) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Low Level' => 'danger',
            'High' => 'success',
            'Medium' => 'warning',
            'Low' => 'danger',
            default => 'secondary'
        };
    }
}