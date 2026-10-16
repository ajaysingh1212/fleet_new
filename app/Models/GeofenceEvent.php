<?php

namespace App\Models;

use App\Enums\GeofenceEventType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeofenceEvent extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'geofence_id',
        'user_id',
        'gps_location_id',
        'type',
        'latitude',
        'longitude',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => GeofenceEventType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'occurred_at' => 'datetime',
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

    public function gpsLocation(): BelongsTo
    {
        return $this->belongsTo(GpsLocation::class);
    }
}
