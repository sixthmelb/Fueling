<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;

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

   // Tambahkan method ini ke FuelStorage model (menggantikan yang lama)

    // IMPROVED: Stock Management with better validation and logging
    public function addFuel(float $amount, string $notes = null): bool
    {
        if ($amount <= 0) {
            Log::error('Invalid fuel amount for addition', [
                'storage_code' => $this->storage_code,
                'amount' => $amount
            ]);
            return false;
        }

        // Refresh model to get latest data
        $this->refresh();

        if (($this->current_level + $amount) > $this->capacity) {
            Log::error('Storage capacity exceeded', [
                'storage_code' => $this->storage_code,
                'current_level' => $this->current_level,
                'amount_to_add' => $amount,
                'capacity' => $this->capacity,
                'overflow' => ($this->current_level + $amount) - $this->capacity
            ]);
            return false;
        }

        $oldLevel = $this->current_level;
        $newLevel = $oldLevel + $amount;
        
        // Use update instead of increment to ensure consistency
        $updated = $this->update(['current_level' => $newLevel]);
        
        if ($updated) {
            Log::info('Fuel added to storage', [
                'storage_code' => $this->storage_code,
                'amount_added' => $amount,
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
                'notes' => $notes
            ]);
        }
        
        return $updated;
    }

    public function removeFuel(float $amount, string $notes = null): bool
    {
        if ($amount <= 0) {
            Log::error('Invalid fuel amount for removal', [
                'storage_code' => $this->storage_code,
                'amount' => $amount
            ]);
            return false;
        }

        // Refresh model to get latest data
        $this->refresh();

        if ($this->current_level < $amount) {
            Log::error('Insufficient fuel for removal', [
                'storage_code' => $this->storage_code,
                'current_level' => $this->current_level,
                'requested_amount' => $amount,
                'shortage' => $amount - $this->current_level
            ]);
            return false;
        }

        $oldLevel = $this->current_level;
        $newLevel = $oldLevel - $amount;
        
        // Use update instead of decrement to ensure consistency
        $updated = $this->update(['current_level' => max(0, $newLevel)]);
        
        if ($updated) {
            Log::info('Fuel removed from storage', [
                'storage_code' => $this->storage_code,
                'amount_removed' => $amount,
                'old_level' => $oldLevel,
                'new_level' => $this->current_level,
                'notes' => $notes
            ]);
        }
        
        return $updated;
    }

    public function updateLevel(float $newLevel): bool
    {
        if ($newLevel < 0 || $newLevel > $this->capacity) {
            Log::error('Invalid fuel level update', [
                'storage_code' => $this->storage_code,
                'new_level' => $newLevel,
                'capacity' => $this->capacity
            ]);
            return false;
        }

        $oldLevel = $this->current_level;
        $updated = $this->update(['current_level' => $newLevel]);
        
        if ($updated) {
            Log::info('Storage level manually updated', [
                'storage_code' => $this->storage_code,
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
                'difference' => $newLevel - $oldLevel
            ]);
        }
        
        return $updated;
    }

    // Tambah method untuk debugging
    public function getCurrentLevelFresh(): float
    {
        return $this->fresh()->current_level;
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

    public function isLowLevel(): bool
    {
        return $this->current_level <= $this->minimum_level;
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

    public function getLatestStockCheck()
    {
        return $this->physicalStockChecks()->latest('check_date')->first();
    }

    // Validation Methods
    public function validateTransferOut(float $amount): array
    {
        $issues = [];
        
        if (!$this->is_active) {
            $issues[] = "Storage {$this->storage_code} is not active";
        }
        
        if ($amount <= 0) {
            $issues[] = "Transfer amount must be greater than 0";
        }
        
        if (!$this->canDispense($amount)) {
            $issues[] = "Insufficient fuel in storage. Available: {$this->current_level}L, Required: {$amount}L";
        }
        
        if ($this->current_level - $amount < 0) {
            $issues[] = "Transfer would result in negative fuel level";
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
        return "{$this->storage_code} - {$this->storage_name}";
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }
        
        if ($this->isEmpty()) {
            return 'Empty';
        }
        
        if ($this->isLowLevel()) {
            return 'Low Level';
        }
        
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

    public function getStatusColorAttribute(): string
    {
        if (!$this->is_active) {
            return 'secondary';
        }
        
        return match ($this->status) {
            'Empty' => 'danger',
            'Low Level' => 'danger',
            'Very Low' => 'danger',
            'Low' => 'warning',
            'Medium' => 'primary',
            'High' => 'success',
            'Nearly Full' => 'success',
            default => 'secondary'
        };
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
        
        return "{$percentage}% used ({$this->formatted_current_level} / {$this->formatted_capacity}) | {$remaining}L remaining";
    }
}