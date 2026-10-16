<?php

namespace App\Models;

use App\Enums\GeofenceAssignmentRunStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceAssignmentRun extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'geofence_assignment_id',
        'run_date',
        'status',
        'visited_at',
        'geofence_event_id',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'run_date' => 'date',
            'status' => GeofenceAssignmentRunStatus::class,
            'visited_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(GeofenceAssignment::class, 'geofence_assignment_id');
    }

    public function geofenceEvent(): BelongsTo
    {
        return $this->belongsTo(GeofenceEvent::class);
    }
}
