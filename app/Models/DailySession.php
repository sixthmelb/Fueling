<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_date',
        'shift_id',
        'session_name',
        'start_datetime',
        'end_datetime',
        'status',
        'notes',
    ];

    protected $casts = [
        'session_date' => 'date',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
    ];

    // Relationships
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function fuelTransfers(): HasMany
    {
        return $this->hasMany(FuelTransfer::class);
    }

    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    public function unitConsumptionSummaries(): HasMany
    {
        return $this->hasMany(UnitConsumptionSummary::class, 'shift_id', 'shift_id')
            ->whereDate('summary_date', $this->session_date);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'Closed');
    }

    public function scopeToday($query)
    {
        return $query->where('session_date', today());
    }

    public function scopeByShift($query, $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('session_date', [$fromDate, $toDate]);
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function isClosed(): bool
    {
        return $this->status === 'Closed';
    }

    public function canBeModified(): bool
    {
        return $this->isActive();
    }

    public function closeSession(string $notes = null): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $this->update([
            'status' => 'Closed',
            'end_datetime' => now(),
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);

        return true;
    }

    public function reopenSession(): bool
    {
        if (!$this->isClosed()) {
            return false;
        }

        $this->update([
            'status' => 'Active',
        ]);

        return true;
    }

    // Analysis Methods
    public function getTotalFuelTransfers(): float
    {
        return $this->fuelTransfers()->sum('transferred_amount') ?? 0;
    }

    public function getTotalFuelTransactions(): float
    {
        return $this->fuelTransactions()->sum('fuel_amount') ?? 0;
    }

    public function getTransfersCount(): int
    {
        return $this->fuelTransfers()->count();
    }

    public function getTransactionsCount(): int
    {
        return $this->fuelTransactions()->count();
    }

    public function getUniqueUnitsCount(): int
    {
        return $this->fuelTransactions()->distinct('unit_id')->count();
    }

    public function getSessionDurationInMinutes(): ?int
    {
        if (!$this->start_datetime || !$this->end_datetime) {
            return null;
        }

        return $this->start_datetime->diffInMinutes($this->end_datetime);
    }

    public function getSessionDurationInHours(): ?float
    {
        $minutes = $this->getSessionDurationInMinutes();
        return $minutes ? round($minutes / 60, 2) : null;
    }

    // Get session statistics
    public function getSessionStats(): array
    {
        return [
            'total_transfers' => $this->getTotalFuelTransfers(),
            'total_transactions' => $this->getTotalFuelTransactions(),
            'transfers_count' => $this->getTransfersCount(),
            'transactions_count' => $this->getTransactionsCount(),
            'unique_units' => $this->getUniqueUnitsCount(),
            'duration_hours' => $this->getSessionDurationInHours(),
            'status' => $this->status,
        ];
    }

    public function getMostActiveUnits($limit = 5)
    {
        return $this->fuelTransactions()
            ->with('unit')
            ->selectRaw('unit_id, COUNT(*) as transaction_count, SUM(fuel_amount) as total_fuel')
            ->groupBy('unit_id')
            ->orderByDesc('total_fuel')
            ->limit($limit)
            ->get();
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return $this->session_name ?: "{$this->session_date->format('Y-m-d')} {$this->shift->shift_name}";
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Active' => 'success',
            'Closed' => 'secondary',
            default => 'primary'
        };
    }

    public function getSessionPeriodAttribute(): string
    {
        if (!$this->start_datetime) {
            return 'Not Started';
        }

        $period = $this->start_datetime->format('H:i');
        
        if ($this->end_datetime) {
            $period .= ' - ' . $this->end_datetime->format('H:i');
        } else {
            $period .= ' - Ongoing';
        }

        return $period;
    }
}