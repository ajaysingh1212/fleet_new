<?php

namespace App\Models;

use App\Enums\GeofenceAssignmentStatus;
use App\Enums\GeofenceScheduleType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeofenceAssignment extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'geofence_id',
        'user_id',
        'assigned_by',
        'route_label',
        'sequence',
        'schedule_type',
        'schedule_days',
        'schedule_date',
        'window_start',
        'window_end',
        'alert_on_exit',
        'alert_on_missed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'schedule_type' => GeofenceScheduleType::class,
            'schedule_days' => 'array',
            'schedule_date' => 'date',
            'alert_on_exit' => 'boolean',
            'alert_on_missed' => 'boolean',
            'status' => GeofenceAssignmentStatus::class,
        ];
    }

    public function geofence(): BelongsTo
    {
        return $this->belongsTo(Geofence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(GeofenceAssignmentRun::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GeofenceAssignmentStatus::Active);
    }
}
