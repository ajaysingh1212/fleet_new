<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldTaskStop extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid', 'field_task_id', 'sequence', 'title', 'description', 'address', 'latitude',
        'longitude', 'expected_at', 'radius_meters', 'status', 'arrived_at',
        'arrival_location_id', 'arrival_distance_meters',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'expected_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo { return $this->belongsTo(FieldTask::class, 'field_task_id'); }
    public function arrivalLocation(): BelongsTo { return $this->belongsTo(GpsLocation::class, 'arrival_location_id'); }
}
