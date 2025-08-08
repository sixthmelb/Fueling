<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_log';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Get the subject model
     */
    public function subject()
    {
        if ($this->subject_type && $this->subject_id) {
            return $this->subject_type::find($this->subject_id);
        }
        return null;
    }

    /**
     * Get the causer model (user)
     */
    public function causer()
    {
        if ($this->causer_type && $this->causer_id) {
            return $this->causer_type::find($this->causer_id);
        }
        return null;
    }

    /**
     * Helper method untuk create activity log
     */
    public static function createLog(
        string $logName,
        string $description,
        Model $subject = null,
        Model $causer = null,
        array $properties = []
    ): self {
        return static::create([
            'log_name' => $logName,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer ? get_class($causer) : null,
            'causer_id' => $causer?->getKey(),
            'properties' => $properties,
            'batch_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    /**
     * Scope untuk filter by log name
     */
    public function scopeLogName($query, $logName)
    {
        return $query->where('log_name', $logName);
    }

    /**
     * Scope untuk filter by subject
     */
    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
                    ->where('subject_id', $subject->getKey());
    }

    /**
     * Scope untuk filter by causer (user)
     */
    public function scopeByCauser($query, Model $causer)
    {
        return $query->where('causer_type', get_class($causer))
                    ->where('causer_id', $causer->getKey());
    }
}