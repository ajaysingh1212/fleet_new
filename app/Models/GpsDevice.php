<?php

namespace App\Models;

use App\Services\Gps\HardwareConnectionState;
use Carbon\CarbonImmutable;
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
        return $this->hasOne(GpsDeviceEvent::class)->ofMany(['received_at' => 'max', 'id' => 'max'], fn ($query) => $query->where('type', 'heartbeat'));
    }

    public function getIsOnlineAttribute(): bool
    {
        $presence = app(HardwareConnectionState::class)->snapshot($this->id);
        $lastPacket = $this->last_packet_at;
        if (! empty($presence['connection_last_packet_at'])) {
            $received = CarbonImmutable::parse($presence['connection_last_packet_at']);
            if (! $lastPacket || $received->gt($lastPacket)) {
                $lastPacket = $received;
            }
        }

        return $this->status === 'active' && $lastPacket?->gt(now()->subSeconds(config('gps.idle_seconds')))
            && ($presence['connection_connected'] ?? true);
    }
}
