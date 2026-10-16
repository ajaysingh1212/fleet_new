<?php

namespace App\Models;

use App\Enums\TrackingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceStatus extends Model
{
    protected $table = 'device_status';

    protected $fillable = [
        'device_session_id',
        'is_online',
        'is_gps_enabled',
        'is_internet_enabled',
        'battery_level',
        'network_type',
        'tracking_mode',
        'last_location_id',
        'last_ping_at',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'is_gps_enabled' => 'boolean',
            'is_internet_enabled' => 'boolean',
            'battery_level' => 'integer',
            'tracking_mode' => TrackingSource::class,
            'last_ping_at' => 'datetime',
        ];
    }

    public function deviceSession(): BelongsTo
    {
        return $this->belongsTo(DeviceSession::class);
    }

    public function lastLocation(): BelongsTo
    {
        return $this->belongsTo(GpsLocation::class, 'last_location_id');
    }
}
