<?php

namespace App\Services\Gps;

use App\DTO\LocationUpdateData;
use App\Events\GpsLocationUpdated;
use App\Models\DeviceSession;
use App\Models\GpsLocation;
use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Support\Facades\Cache;

class LiveLocationService
{
    public function latest(int $userId): ?GpsLocation
    {
        $attributes = Cache::get('gps.live.'.$userId);

        return $attributes ? new GpsLocation($attributes) : null;
    }

    public function publish(User $user, DeviceSession $device, LocationUpdateData $dto): void
    {
        $device->update(['last_activity_at' => now()]);
        app(UserPresenceService::class)->broadcastChange($user->id);

        // Historical offline uploads must not rewind the live map.
        if ($dto->recordedAt->lt(now()->subMinutes(5)) || $dto->recordedAt->gt(now()->addSeconds(30))) {
            return;
        }

        Cache::lock('gps.live.lock.'.$user->id, 10)->block(3, function () use ($user, $device, $dto) {
            $previous = $this->latest($user->id);
            if ($previous && $previous->recorded_at->gte($dto->recordedAt)) {
                return;
            }
            $attributes = [
                'user_id' => $user->id,
                'device_session_id' => $device->id,
                'tracking_session_id' => null,
                'source_type' => $dto->sourceType->value,
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
                'accuracy' => $dto->accuracy,
                'speed' => $dto->speed,
                'bearing' => $dto->bearing,
                'heading' => $dto->heading,
                'altitude' => $dto->altitude,
                'battery_level' => $dto->batteryLevel,
                'network_type' => $dto->networkType,
                'recorded_at' => $dto->recordedAt->toIso8601String(),
            ];
            \Illuminate\Support\Facades\DB::transaction(fn () =>
                app(\App\Services\Geofence\GeofenceEvaluationService::class)->evaluate(new GpsLocation($attributes)));
            Cache::put('gps.live.'.$user->id, $attributes, now()->addDay());
            GpsLocationUpdated::dispatch(new GpsLocation($attributes));
        });
    }
}
