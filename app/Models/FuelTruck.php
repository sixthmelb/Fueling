<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;

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

    // IMPROVED: Stock Management with better validation and logging
    public function addFuel(float $amount, string $notes = null): bool
    {
        if ($amount <= 0) {
            Log::error('Invalid fuel amount for addition', [
                'truck_code' => $this->truck_code,
                'amount' => $amount
            ]);
            return false;
        }

        if (($this->current_level + $amount) > $this->capacity) {
            Log::error('Truck capacity exceeded', [
                'truck_code' => $this->truck_code,
                'current_level' => $this->current_level,
                'amount_to_add' => $amount,
                'capacity' => $this->capacity,
                'overflow' => ($this->current_level + $amount) - $this->capacity
            ]);
            return false;
        }

        $oldLevel = $this->current_level;
        $this->increment('current_level', $amount);
        
        Log::info('Fuel added to truck', [
            'truck_code' => $this->truck_code,
            'amount_added' => $amount,
            'old_level' => $oldLevel,
            'new_level' => $this->current_level,
            'notes' => $notes
        ]);
        
        return true;
    }

    public function removeFuel(float $amount, string $notes = null): bool
    {
        if ($amount <= 0) {
            Log::error('Invalid fuel amount for removal', [
                'truck_code' => $this->truck_code,
                'amount' => $amount
            ]);
            return false;
        }

        if ($this->current_level < $amount) {
            Log::error('Insufficient fuel for removal', [
                'truck_code' => $this->truck_code,
                'current_level' => $this->current_level,
                'requested_amount' => $amount,
                'shortage' => $amount - $this->current_level
            ]);
            return false;
        }

        $oldLevel = $this->current_level;
        $this->decrement('current_level', $amount);
        
        Log::info('Fuel removed from truck', [
            'truck_code' => $this->truck_code,
            'amount_removed' => $amount,
            'old_level' => $oldLevel,
            'new_level' => $this->current_level,
            'notes' => $notes
        ]);
        
        return true;
    }

    public function updateLevel(float $newLevel): bool
    {
        if ($newLevel < 0 || $newLevel > $this->capacity) {
            Log::error('Invalid fuel level update', [
                'truck_code' => $this->truck_code,
                'new_level' => $newLevel,
                'capacity' => $this->capacity
            ]);
            return false;
        }

        $oldLevel = $this->current_level;
        $this->update(['current_level' => $newLevel]);
        
        Log::info('Truck level manually updated', [
            'truck_code' => $this->truck_code,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'difference' => $newLevel - $oldLevel
        ]);
        
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
        return max(0, $this->capacity - $this->current_level);
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
        return $this->physicalStockChecks()->latest('check_date')->first();
    }

    // Validation Methods
    public function validateTransferIn(float $amount): array
    {
        $issues = [];
        
        if (!$this->is_active) {
            $issues[] = "Truck {$this->truck_code} is not active";
        }
        
        if ($amount <= 0) {
            $issues[] = "Transfer amount must be greater than 0";
        }
        
        if (!$this->canReceive($amount)) {
            $available = $this->getRemainingCapacity();
            $issues[] = "Insufficient truck capacity. Available: {$available}L, Required: {$amount}L";
        }
        
        if ($this->current_level + $amount > $this->capacity) {
            $issues[] = "Transfer would exceed truck capacity";
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'available_capacity' => $this->getRemainingCapacity(),
            'requested' => $amount,
            'level_after' => min($this->capacity, $this->current_level + $amount)
        ];
    }

    public function validateDistribution(float $amount): array
    {
        $issues = [];
        
        if (!$this->is_active) {
            $issues[] = "Truck {$this->truck_code} is not active";
        }
        
        if ($amount <= 0) {
            $issues[] = "Distribution amount must be greater than 0";
        }
        
        if (!$this->canDispense($amount)) {
            $issues[] = "Insufficient fuel in truck. Available: {$this->current_level}L, Required: {$amount}L";
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'available' => $this->current_level,
            'requested' => $amount,
            'remaining_after' => max(0, $this->current_level - $amount)
        ];
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
        if (!$this->is_active) {
            return 'Inactive';
        }
        
        if ($this->isEmpty()) {
            return 'Empty';
        } elseif ($this->isFull()) {
            return 'Full';
        } else {
            $percentage = $this->getCapacityUsagePercentage();
            if ($percentage >= 90) {
                return 'Nearly Full';
            } elseif ($percentage >= 75) {
                return 'High';
            } elseif ($percentage >= 50) {
                return 'Medium';
            } elseif ($percentage >= 25) {
                return 'Low';
            } else {
                return 'Very Low';
            }
        }
    }

    public function getStatusColorAttribute(): string
    {
        if (!$this->is_active) {
            return 'secondary';
        }
        
        return match ($this->status) {
            'Empty' => 'danger',
            'Very Low' => 'danger',
            'Low' => 'warning',
            'Medium' => 'primary',
            'High' => 'success',
            'Nearly Full' => 'success',
            'Full' => 'success',
            default => 'secondary'
        };
    }

    public function getAgeAttribute(): ?int
    {
        return $this->manufacture_year ? now()->year - $this->manufacture_year : null;
    }

    public function getFormattedCapacityAttribute(): string
    {
        return number_format($this->capacity, 0) . 'L';
    }

    public function getFormattedCurrentLevelAttribute(): string
    {
        return number_format($this->current_level, 2) . 'L';
    }

    public function getUsageDescriptionAttribute(): string
    {
        $percentage = $this->getCapacityUsagePercentage();
        $remaining = $this->getRemainingCapacity();
        
        return "{$percentage}% used ({$this->formatted_current_level} / {$this->formatted_capacity}) | {$remaining}L available";
    }

    public function getEfficiencyStatsAttribute(): array
    {
        $distributions = $this->fuelDistributions()
            ->where('transaction_datetime', '>=', now()->subDays(30))
            ->get();
        
        return [
            'total_distributions' => $distributions->count(),
            'total_fuel_distributed' => $distributions->sum('fuel_amount'),
            'average_per_distribution' => $distributions->count() > 0 
                ? $distributions->avg('fuel_amount') 
                : 0,
            'last_distribution' => $this->getLatestDistribution()?->transaction_datetime
        ];
    }
}