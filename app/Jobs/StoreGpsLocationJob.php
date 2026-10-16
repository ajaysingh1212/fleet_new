<?php

namespace App\Jobs;

use App\DTO\LocationUpdateData;
use App\Events\LocationStored;
use App\Models\DeviceStatus;
use App\Models\GpsLocation;
use App\Models\MovementEvent;
use App\Models\TrackingSession;
use App\Services\Gps\CoordinateOptimizerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StoreGpsLocationJob implements ShouldQueue
{
    use Queueable;

    private const STOP_SPEED_THRESHOLD = 0.5;

    private const SPEED_CHANGE_THRESHOLD = 5.0;

    private const DIRECTION_CHANGE_THRESHOLD = 45.0;

    public function __construct(
        public int $userId,
        public int $deviceSessionId,
        public int $trackingSessionId,
        public LocationUpdateData $location,
    ) {}

    public function handle(CoordinateOptimizerService $optimizer): void
    {
        \Illuminate\Support\Facades\Cache::lock('gps.store.'.$this->deviceSessionId, 15)
            ->block(5, fn () => \Illuminate\Support\Facades\DB::transaction(fn () => $this->store($optimizer)));
    }

    private function store(CoordinateOptimizerService $optimizer): void
    {
        $status = DeviceStatus::query()->where('device_session_id', $this->deviceSessionId)->first();
        $previous = $status?->lastLocation;

        $user = \App\Models\User::findOrFail($this->userId);
        $radius = app(\App\Services\SettingsService::class)->locationSaveRadius($user);
        if (($previous && $this->location->recordedAt->lte($previous->recorded_at))
            || ! $optimizer->shouldAccept($status, $this->location, $radius)->accepted) {
            return;
        }

        $gpsLocation = GpsLocation::create([
            'user_id' => $this->userId,
            'device_session_id' => $this->deviceSessionId,
            'tracking_session_id' => $this->trackingSessionId,
            'source_type' => $this->location->sourceType,
            'latitude' => $this->location->latitude,
            'longitude' => $this->location->longitude,
            'accuracy' => $this->location->accuracy,
            'speed' => $this->location->speed,
            'bearing' => $this->location->bearing,
            'heading' => $this->location->heading,
            'altitude' => $this->location->altitude,
            'battery_level' => $this->location->batteryLevel,
            'network_type' => $this->location->networkType,
            'signal_strength' => $this->location->signalStrength,
            'provider' => $this->location->provider,
            'is_mock' => $this->location->isMock,
            'recorded_at' => $this->location->recordedAt,
        ]);

        DeviceStatus::query()->updateOrCreate(
            ['device_session_id' => $this->deviceSessionId],
            [
                'is_online' => true,
                'is_gps_enabled' => true,
                // Reaching this handler means the packet made it to the server, so the
                // device necessarily had internet at send time.
                'is_internet_enabled' => true,
                'battery_level' => $this->location->batteryLevel,
                'network_type' => $this->location->networkType,
                'last_location_id' => $gpsLocation->id,
                'last_ping_at' => $this->location->recordedAt,
            ],
        );

        $this->updateTrackingSession($optimizer, $previous, $gpsLocation);
        $this->recordMovementEvent($previous, $gpsLocation);

        LocationStored::dispatch($gpsLocation);
    }

    private function updateTrackingSession(CoordinateOptimizerService $optimizer, ?GpsLocation $previous, GpsLocation $current): void
    {
        $session = TrackingSession::query()->find($this->trackingSessionId);

        if (! $session) {
            return;
        }

        $distance = $previous
            ? $optimizer->distanceInMeters(
                (float) $previous->latitude,
                (float) $previous->longitude,
                (float) $current->latitude,
                (float) $current->longitude,
            )
            : 0.0;

        $totalDistance = $session->total_distance_meters + (int) round($distance);
        $elapsedSeconds = max(1, abs($current->recorded_at->diffInSeconds($session->started_at)));
        $averageSpeed = $totalDistance / $elapsedSeconds;
        $maxSpeed = max((float) ($session->max_speed ?? 0), (float) ($current->speed ?? 0));

        $session->update([
            'total_distance_meters' => $totalDistance,
            'average_speed' => round($averageSpeed, 2),
            'max_speed' => round($maxSpeed, 2),
        ]);
    }

    private function recordMovementEvent(?GpsLocation $previous, GpsLocation $current): void
    {
        if (! $previous) {
            return;
        }

        $previousSpeed = (float) ($previous->speed ?? 0);
        $currentSpeed = (float) ($current->speed ?? 0);

        $eventType = match (true) {
            $previousSpeed > self::STOP_SPEED_THRESHOLD && $currentSpeed <= self::STOP_SPEED_THRESHOLD => 'stop',
            $previousSpeed <= self::STOP_SPEED_THRESHOLD && $currentSpeed > self::STOP_SPEED_THRESHOLD => 'resume',
            abs($currentSpeed - $previousSpeed) > self::SPEED_CHANGE_THRESHOLD => 'speed_change',
            $current->bearing !== null && $previous->bearing !== null
                && $this->bearingDelta((float) $current->bearing, (float) $previous->bearing) > self::DIRECTION_CHANGE_THRESHOLD => 'direction_change',
            default => null,
        };

        if (! $eventType) {
            return;
        }

        MovementEvent::create([
            'tracking_session_id' => $this->trackingSessionId,
            'event_type' => $eventType,
            'latitude' => $current->latitude,
            'longitude' => $current->longitude,
            'speed' => $current->speed,
            'bearing' => $current->bearing,
            'occurred_at' => $current->recorded_at,
        ]);
    }

    private function bearingDelta(float $a, float $b): float
    {
        $diff = abs($a - $b);

        return $diff > 180 ? 360 - $diff : $diff;
    }
}
