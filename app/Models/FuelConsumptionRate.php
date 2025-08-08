<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FuelConsumptionRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_type_id',
        'consumption_per_hour',
        'consumption_per_km',
        'effective_from',
        'effective_until',
        'work_condition',
        'condition_description',
        'rate_source',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'consumption_per_hour' => 'decimal:2',
        'consumption_per_km' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        return $query->where('effective_from', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('effective_until')
                          ->orWhere('effective_until', '>=', now());
                    });
    }

    public function scopeByUnitType($query, $unitTypeId)
    {
        return $query->where('unit_type_id', $unitTypeId);
    }

    public function scopeByWorkCondition($query, $condition)
    {
        return $query->where('work_condition', $condition);
    }

    public function scopeByRateSource($query, $source)
    {
        return $query->where('rate_source', $source);
    }

    public function scopeEffectiveOn($query, $date)
    {
        return $query->where('effective_from', '<=', $date)
                    ->where(function ($q) use ($date) {
                        $q->whereNull('effective_until')
                          ->orWhere('effective_until', '>=', $date);
                    });
    }

    // Helper Methods
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = now()->toDateString();
        
        return $this->effective_from <= $today && 
               ($this->effective_until === null || $this->effective_until >= $today);
    }

    public function isExpired(): bool
    {
        return $this->effective_until && $this->effective_until < now()->toDateString();
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->effective_until) {
            return null; // Never expires
        }

        return now()->diffInDays($this->effective_until, false);
    }

    public function getDaysActive(): int
    {
        $startDate = Carbon::parse($this->effective_from);
        $endDate = $this->effective_until ? Carbon::parse($this->effective_until) : now();
        
        return $startDate->diffInDays($endDate);
    }

    public function extendValidity(string $newEffectiveUntil, string $notes = null): bool
    {
        $this->update([
            'effective_until' => $newEffectiveUntil,
            'notes' => $notes ? ($this->notes . "\n" . $notes) : $this->notes,
            'updated_by' => auth()->user()?->name ?? 'System',
        ]);

        return true;
    }

    public function deactivate(string $reason = null): bool
    {
        $this->update([
            'is_active' => false,
            'effective_until' => now()->toDateString(),
            'notes' => $reason ? ($this->notes . "\nDeactivated: " . $reason) : $this->notes,
            'updated_by' => auth()->user()?->name ?? 'System',
        ]);

        return true;
    }

    // Analysis Methods
    public function calculateExpectedConsumption(float $hours, float $km): float
    {
        $hourlyConsumption = $hours * $this->consumption_per_hour;
        $kmConsumption = $km * $this->consumption_per_km;
        
        return round($hourlyConsumption + $kmConsumption, 2);
    }

    public function compareWithActual(float $actualFuel, float $hours, float $km): array
    {
        $expected = $this->calculateExpectedConsumption($hours, $km);
        $variance = $expected > 0 ? (($actualFuel - $expected) / $expected) * 100 : 0;
        
        return [
            'expected' => $expected,
            'actual' => $actualFuel,
            'variance' => round($variance, 2),
            'variance_amount' => round($actualFuel - $expected, 2),
            'efficiency_status' => $this->getEfficiencyStatus($variance),
        ];
    }

    private function getEfficiencyStatus(float $variance): string
    {
        if ($variance <= -15) {
            return 'Excellent';
        } elseif ($variance <= -5) {
            return 'Good';
        } elseif ($variance <= 5) {
            return 'Normal';
        } elseif ($variance <= 15) {
            return 'High';
        } else {
            return 'Very High';
        }
    }

    public function getUnitsUsingThisRate()
    {
        return $this->unitType->units()->active();
    }

    // Validation Methods
    public static function hasOverlappingRate($unitTypeId, $workCondition, $effectiveFrom, $effectiveUntil = null, $excludeId = null): bool
    {
        $query = static::where('unit_type_id', $unitTypeId)
                      ->where('work_condition', $workCondition)
                      ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        // Check for overlapping periods
        $query->where(function ($q) use ($effectiveFrom, $effectiveUntil) {
            $q->where(function ($subQ) use ($effectiveFrom) {
                // New start date falls within existing period
                $subQ->where('effective_from', '<=', $effectiveFrom)
                     ->where(function ($innerQ) use ($effectiveFrom) {
                         $innerQ->whereNull('effective_until')
                                ->orWhere('effective_until', '>=', $effectiveFrom);
                     });
            });

            if ($effectiveUntil) {
                $q->orWhere(function ($subQ) use ($effectiveUntil) {
                    // New end date falls within existing period
                    $subQ->where('effective_from', '<=', $effectiveUntil)
                         ->where(function ($innerQ) use ($effectiveUntil) {
                             $innerQ->whereNull('effective_until')
                                    ->orWhere('effective_until', '>=', $effectiveUntil);
                         });
                });
            }
        });

        return $query->exists();
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return "{$this->unitType->type_name} - {$this->work_condition}";
    }

    public function getEffectivePeriodAttribute(): string
    {
        $period = $this->effective_from->format('d/m/Y');
        
        if ($this->effective_until) {
            $period .= ' - ' . $this->effective_until->format('d/m/Y');
        } else {
            $period .= ' - Ongoing';
        }
        
        return $period;
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        if ($this->isCurrentlyActive()) {
            return 'Active';
        }

        return 'Scheduled';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Active' => 'success',
            'Scheduled' => 'primary',
            'Expired' => 'warning',
            'Inactive' => 'secondary',
            default => 'secondary'
        };
    }

    public function getExpiryWarningAttribute(): ?string
    {
        $daysUntilExpiry = $this->getDaysUntilExpiry();
        
        if ($daysUntilExpiry === null) {
            return null;
        }

        if ($daysUntilExpiry < 0) {
            return 'Expired ' . abs($daysUntilExpiry) . ' days ago';
        } elseif ($daysUntilExpiry <= 7) {
            return 'Expires in ' . $daysUntilExpiry . ' days';
        } elseif ($daysUntilExpiry <= 30) {
            return 'Expires in ' . $daysUntilExpiry . ' days';
        }

        return null;
    }
}