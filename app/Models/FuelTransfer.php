<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    // Validation Methods
    public function isValidTransfer(): bool
    {
        // Check if storage has enough fuel
        if ($this->storage_level_before < $this->transferred_amount) {
            return false;
        }

        // Check if truck has enough capacity
        if (($this->truck_level_before + $this->transferred_amount) > $this->fuelTruck->capacity) {
            return false;
        }

        // Validate level calculations
        $expectedStorageAfter = $this->storage_level_before - $this->transferred_amount;
        $expectedTruckAfter = $this->truck_level_before + $this->transferred_amount;

        if (abs($this->storage_level_after - $expectedStorageAfter) > 0.01) {
            return false;
        }

        if (abs($this->truck_level_after - $expectedTruckAfter) > 0.01) {
            return false;
        }

        return true;
    }

    public function getTransferEfficiency(): float
    {
        // Calculate efficiency based on expected vs actual levels
        $expectedStorageLevel = $this->storage_level_before - $this->transferred_amount;
        $expectedTruckLevel = $this->truck_level_before + $this->transferred_amount;
        
        $storageVariance = abs($this->storage_level_after - $expectedStorageLevel);
        $truckVariance = abs($this->truck_level_after - $expectedTruckLevel);
        
        $totalVariance = $storageVariance + $truckVariance;
        $efficiency = max(0, 100 - (($totalVariance / $this->transferred_amount) * 100));
        
        return round($efficiency, 2);
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
        $efficiency = $this->getTransferEfficiency();
        
        if ($efficiency >= 99) {
            return 'Excellent';
        } elseif ($efficiency >= 95) {
            return 'Good';
        } elseif ($efficiency >= 90) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }

    public function getEfficiencyColorAttribute(): string
    {
        return match ($this->efficiency_status) {
            'Excellent' => 'success',
            'Good' => 'primary',
            'Fair' => 'warning',
            'Poor' => 'danger',
            default => 'secondary'
        };
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->transfer_datetime->format('d/m/Y H:i');
    }
}