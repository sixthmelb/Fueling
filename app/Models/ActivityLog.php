<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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

    // Scopes
    public function scopeLogName($query, $logName)
    {
        return $query->where('log_name', $logName);
    }

    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
                    ->where('subject_id', $subject->getKey());
    }

    public function scopeByCauser($query, Model $causer)
    {
        return $query->where('causer_type', get_class($causer))
                    ->where('causer_id', $causer->getKey());
    }

    public function scopeByUser($query, $userId = null)
    {
        $userId = $userId ?? auth()->id();
        return $query->where('causer_type', User::class)
                    ->where('causer_id', $userId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeDateRange($query, $fromDate, $toDate)
    {
        return $query->whereBetween('created_at', [$fromDate, $toDate]);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    // Relationships (using helper methods since we're not using morphs)
    public function subject()
    {
        if ($this->subject_type && $this->subject_id) {
            try {
                if (class_exists($this->subject_type)) {
                    return $this->subject_type::find($this->subject_id);
                }
            } catch (\Exception $e) {
                Log::warning('Could not load subject for activity log', [
                    'log_id' => $this->id,
                    'subject_type' => $this->subject_type,
                    'subject_id' => $this->subject_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        return null;
    }

    public function causer()
    {
        if ($this->causer_type && $this->causer_id) {
            try {
                if (class_exists($this->causer_type)) {
                    return $this->causer_type::find($this->causer_id);
                }
            } catch (\Exception $e) {
                Log::warning('Could not load causer for activity log', [
                    'log_id' => $this->id,
                    'causer_type' => $this->causer_type,
                    'causer_id' => $this->causer_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        return null;
    }

    // IMPROVED: Helper method untuk create activity log with better validation
    public static function createLog(
        string $logName,
        string $description,
        Model $subject = null,
        Model $causer = null,
        array $properties = []
    ): self {
        try {
            // Validate log name
            if (empty($logName)) {
                throw new \InvalidArgumentException('Log name cannot be empty');
            }

            // Validate description
            if (empty($description)) {
                throw new \InvalidArgumentException('Description cannot be empty');
            }

            // Prepare data
            $data = [
                'log_name' => $logName,
                'description' => $description,
                'properties' => $properties,
                'batch_uuid' => (string) \Illuminate\Support\Str::uuid(),
            ];

            // Add subject information
            if ($subject) {
                $data['subject_type'] = get_class($subject);
                $data['subject_id'] = $subject->getKey();
            }

            // Add causer information
            if ($causer) {
                $data['causer_type'] = get_class($causer);
                $data['causer_id'] = $causer->getKey();
            } elseif (auth()->check()) {
                // Default to current user if no causer specified
                $data['causer_type'] = get_class(auth()->user());
                $data['causer_id'] = auth()->id();
            }

            $log = static::create($data);

            Log::info('Activity log created', [
                'log_id' => $log->id,
                'log_name' => $logName,
                'subject' => $subject ? get_class($subject) . ':' . $subject->getKey() : null,
                'causer' => $causer ? get_class($causer) . ':' . $causer->getKey() : null
            ]);

            return $log;

        } catch (\Exception $e) {
            Log::error('Failed to create activity log', [
                'log_name' => $logName,
                'description' => $description,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    // Bulk logging methods
    public static function createBulkLog(
        string $logName,
        string $description,
        array $subjects = [],
        Model $causer = null,
        array $commonProperties = []
    ): array {
        $logs = [];
        $batchUuid = (string) \Illuminate\Support\Str::uuid();

        foreach ($subjects as $subject) {
            try {
                $properties = array_merge($commonProperties, [
                    'batch_operation' => true,
                    'batch_size' => count($subjects)
                ]);

                $data = [
                    'log_name' => $logName,
                    'description' => $description,
                    'subject_type' => get_class($subject),
                    'subject_id' => $subject->getKey(),
                    'properties' => $properties,
                    'batch_uuid' => $batchUuid,
                ];

                if ($causer) {
                    $data['causer_type'] = get_class($causer);
                    $data['causer_id'] = $causer->getKey();
                } elseif (auth()->check()) {
                    $data['causer_type'] = get_class(auth()->user());
                    $data['causer_id'] = auth()->id();
                }

                $logs[] = static::create($data);

            } catch (\Exception $e) {
                Log::error('Failed to create bulk activity log', [
                    'log_name' => $logName,
                    'subject' => get_class($subject) . ':' . $subject->getKey(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Bulk activity logs created', [
            'log_name' => $logName,
            'batch_uuid' => $batchUuid,
            'count' => count($logs)
        ]);

        return $logs;
    }

    // Query helpers
    public static function getRecentActivity($limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['subject', 'causer'])
                    ->latest()
                    ->limit($limit)
                    ->get();
    }

    public static function getActivityForSubject(Model $subject, $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return static::forSubject($subject)
                    ->latest()
                    ->limit($limit)
                    ->get();
    }

    public static function getActivityByLogName(string $logName, $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::logName($logName)
                    ->latest()
                    ->limit($limit)
                    ->get();
    }

    public static function getActivityStats($dateFrom = null, $dateTo = null): array
    {
        $query = static::query();

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $totalLogs = $query->count();
        
        $logsByName = $query->selectRaw('log_name, COUNT(*) as count')
                           ->groupBy('log_name')
                           ->pluck('count', 'log_name')
                           ->toArray();

        $logsByUser = $query->selectRaw('causer_type, causer_id, COUNT(*) as count')
                           ->whereNotNull('causer_type')
                           ->whereNotNull('causer_id')
                           ->groupBy('causer_type', 'causer_id')
                           ->get()
                           ->map(function ($item) {
                               try {
                                   $user = $item->causer_type::find($item->causer_id);
                                   return [
                                       'user' => $user ? $user->name : 'Unknown User',
                                       'count' => $item->count
                                   ];
                               } catch (\Exception $e) {
                                   return [
                                       'user' => 'System',
                                       'count' => $item->count
                                   ];
                               }
                           })
                           ->toArray();

        return [
            'total_logs' => $totalLogs,
            'logs_by_name' => $logsByName,
            'logs_by_user' => $logsByUser,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }

    // Search and filtering
    public static function searchLogs(string $searchTerm, $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::where(function ($query) use ($searchTerm) {
                        $query->where('log_name', 'like', "%{$searchTerm}%")
                              ->orWhere('description', 'like', "%{$searchTerm}%")
                              ->orWhere('properties', 'like', "%{$searchTerm}%");
                    })
                    ->latest()
                    ->limit($limit)
                    ->get();
    }

    // Cleanup methods
    public static function cleanupOldLogs($daysToKeep = 90): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedCount = static::where('created_at', '<', $cutoffDate)->delete();

        Log::info('Activity logs cleanup completed', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString()
        ]);

        return $deletedCount;
    }

    // Attributes and helpers
    public function getSubjectNameAttribute(): string
    {
        $subject = $this->subject();
        
        if (!$subject) {
            return 'Unknown Subject';
        }

        if (method_exists($subject, 'getDisplayNameAttribute') || isset($subject->display_name)) {
            return $subject->display_name;
        }

        if (isset($subject->name)) {
            return $subject->name;
        }

        if (method_exists($subject, 'getNameAttribute')) {
            return $subject->name;
        }

        return class_basename($this->subject_type) . ' #' . $this->subject_id;
    }

    public function getCauserNameAttribute(): string
    {
        $causer = $this->causer();
        
        if (!$causer) {
            return 'System';
        }

        if (isset($causer->name)) {
            return $causer->name;
        }

        return class_basename($this->causer_type) . ' #' . $this->causer_id;
    }

    public function getFormattedPropertiesAttribute(): string
    {
        if (!$this->properties || empty($this->properties)) {
            return 'No additional data';
        }

        $formatted = [];
        foreach ($this->properties as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $formatted[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
        }

        return implode(', ', $formatted);
    }

    public function getAgeAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getFormattedDateTimeAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i:s');
    }

    public function getLogTypeAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->log_name));
    }

    public function getLogSummaryAttribute(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->log_type,
            'description' => $this->description,
            'subject' => $this->subject_name,
            'causer' => $this->causer_name,
            'datetime' => $this->formatted_date_time,
            'age' => $this->age,
            'properties' => $this->formatted_properties
        ];
    }

    public function hasProperty(string $key): bool
    {
        return isset($this->properties[$key]);
    }

    public function getProperty(string $key, $default = null)
    {
        return $this->properties[$key] ?? $default;
    }

    public function isPartOfBatch(): bool
    {
        return !empty($this->batch_uuid) && 
               static::where('batch_uuid', $this->batch_uuid)->count() > 1;
    }

    public function getBatchSiblingsCount(): int
    {
        if (!$this->batch_uuid) {
            return 0;
        }

        return static::where('batch_uuid', $this->batch_uuid)
                    ->where('id', '!=', $this->id)
                    ->count();
    }
}