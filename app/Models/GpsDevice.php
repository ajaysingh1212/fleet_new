<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GpsDevice extends Model
{
    // Request-only result, never persisted or accepted from a form.
    public bool $allowlistSyncPending = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array', 'configuration' => 'array', 'last_packet_at' => 'immutable_datetime',
            'last_connected_at' => 'immutable_datetime', 'configuration_updated_at' => 'immutable_datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function latestLocation(): BelongsTo
    {
        return $this->belongsTo(GpsDeviceLocation::class, 'latest_location_id');
    }

    public function latestHeartbeat(): HasOne
    {
        return $this->hasOne(GpsDeviceEvent::class)->ofMany(['id' => 'max'], fn ($query) => $query->where('type', 'heartbeat'));
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->status === 'active' && $this->last_packet_at?->gt(now()->subSeconds(config('gps.idle_seconds')));
    }
}
