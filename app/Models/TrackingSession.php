<?php

namespace App\Models;

use App\Enums\SourceType;
use App\Enums\TrackingSessionStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingSession extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'device_session_id',
        'source_type',
        'started_at',
        'ended_at',
        'total_distance_meters',
        'average_speed',
        'max_speed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => SourceType::class,
            'status' => TrackingSessionStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_distance_meters' => 'integer',
            'average_speed' => 'decimal:2',
            'max_speed' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deviceSession(): BelongsTo
    {
        return $this->belongsTo(DeviceSession::class);
    }

    public function gpsLocations(): HasMany
    {
        return $this->hasMany(GpsLocation::class);
    }

    public function movementEvents(): HasMany
    {
        return $this->hasMany(MovementEvent::class);
    }
}
