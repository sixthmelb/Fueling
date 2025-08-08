<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_code',
        'shift_name',
        'start_time',
        'end_time',
        'description',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function dailySessions(): HasMany
    {
        return $this->hasMany(DailySession::class);
    }

    public function unitConsumptionSummaries(): HasMany
    {
        return $this->hasMany(UnitConsumptionSummary::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrderByStartTime($query)
    {
        return $query->orderBy('start_time');
    }

    // Helper Methods
    public function todaySession()
    {
        return $this->dailySessions()->where('session_date', today())->first();
    }

    public function getOrCreateTodaySession(): DailySession
    {
        return $this->dailySessions()->firstOrCreate([
            'session_date' => today(),
            'shift_id' => $this->id,
        ], [
            'session_name' => today()->format('Y-m-d') . ' ' . $this->shift_name,
            'start_datetime' => today()->setTimeFromTimeString($this->start_time->format('H:i:s')),
            'end_datetime' => today()->setTimeFromTimeString($this->end_time->format('H:i:s')),
            'status' => 'Active',
        ]);
    }

    public function isCurrentlyActive(): bool
    {
        $now = now()->format('H:i:s');
        $startTime = $this->start_time->format('H:i:s');
        $endTime = $this->end_time->format('H:i:s');

        // Handle overnight shifts
        if ($startTime > $endTime) {
            return $now >= $startTime || $now <= $endTime;
        }

        return $now >= $startTime && $now <= $endTime;
    }

    public function getCurrentActiveSession(): ?DailySession
    {
        if (!$this->isCurrentlyActive()) {
            return null;
        }

        return $this->todaySession();
    }

    // Duration calculations
    public function getDurationInMinutes(): int
    {
        $start = Carbon::createFromFormat('H:i:s', $this->start_time->format('H:i:s'));
        $end = Carbon::createFromFormat('H:i:s', $this->end_time->format('H:i:s'));

        // Handle overnight shifts
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        return $start->diffInMinutes($end);
    }

    public function getDurationInHours(): float
    {
        return round($this->getDurationInMinutes() / 60, 2);
    }

    // Analysis Methods
    public function getSessionsCount($dateFrom = null, $dateTo = null): int
    {
        $query = $this->dailySessions();
        
        if ($dateFrom) {
            $query->where('session_date', '>=', $dateFrom);
        }
        
        if ($dateTo) {
            $query->where('session_date', '<=', $dateTo);
        }
        
        return $query->count();
    }

    public function getActiveSessionsCount(): int
    {
        return $this->dailySessions()->where('status', 'Active')->count();
    }

    public function getLastSession()
    {
        return $this->dailySessions()->latest('session_date')->first();
    }

    // Attributes
    public function getDisplayNameAttribute(): string
    {
        return "{$this->shift_code} - {$this->shift_name}";
    }

    public function getTimeRangeAttribute(): string
    {
        return $this->start_time->format('H:i') . ' - ' . $this->end_time->format('H:i');
    }

    public function getFullDescriptionAttribute(): string
    {
        $desc = $this->display_name;
        $desc .= " ({$this->time_range})";
        
        if ($this->description) {
            $desc .= " - {$this->description}";
        }
        
        return $desc;
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'Inactive';
        }

        return $this->isCurrentlyActive() ? 'Active Now' : 'Scheduled';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'Active Now' => 'success',
            'Scheduled' => 'primary',
            'Inactive' => 'secondary',
            default => 'secondary'
        };
    }
}