<?php

namespace App\Services\Gps\Gateway;

use App\Models\GpsDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DeviceAllowlist
{
    public function store()
    {
        return Cache::store(config('gps.cache_store'));
    }

    public function sync(GpsDevice $device): void
    {
        $this->store()->put('gps.hardware.auth.'.$device->identifier, [
            'id' => $device->id, 'protocol' => $device->protocol, 'version' => $device->protocol_version,
            'active' => $device->status === 'active', 'vehicle_id' => $device->vehicle_id,
            'revision' => $device->updated_at->format('U.u'),
        ], now()->addDay());
    }

    public function syncCurrent(int $deviceId, ?string $oldIdentifier = null): void
    {
        // SQL locks serialize publication against admin saves and other sync
        // processes. Always reload: a delayed publisher must not restore stale
        // authorization. This runs outside the TCP connection loop.
        DB::transaction(function () use ($deviceId, $oldIdentifier) {
            $device = GpsDevice::lockForUpdate()->findOrFail($deviceId);
            if ($oldIdentifier !== null && $oldIdentifier !== $device->identifier) {
                $this->store()->forget('gps.hardware.auth.'.$oldIdentifier);
            }
            $this->sync($device);
        });
    }

    public function find(string $identifier): ?array
    {
        return $this->store()->get('gps.hardware.auth.'.$identifier);
    }
}
