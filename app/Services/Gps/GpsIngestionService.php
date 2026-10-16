<?php

namespace App\Services\Gps;

use App\DTO\LocationUpdateData;
use App\Enums\DiagnosticEventType;
use App\Enums\TrackingSessionStatus;
use App\Enums\UserStatus;
use App\Events\LocationReceived;
use App\Events\LocationValidated;
use App\Jobs\StoreGpsLocationJob;
use App\Models\DeviceSession;
use App\Models\DeviceStatus;
use App\Models\DiagnosticLog;
use App\Models\TrackingSession;
use App\Models\User;
use App\Notifications\DiagnosticAlertNotification;

class GpsIngestionService
{
    private const SESSION_GAP_SECONDS = 600;

    private const POOR_ACCURACY_METERS = 100.0;

    /**
     * ~300 km/h — faster than any ordinary ground vehicle can cover between
     * two consecutive fixes. A jump past this is a strong signal the position
     * was spoofed/mocked rather than genuinely travelled, regardless of what
     * the client's own (trivially fakeable) is_mock flag claims.
     */
    private const IMPOSSIBLE_SPEED_MPS = 83.3;

    public function __construct(
        protected CoordinateOptimizerService $optimizer,
    ) {}

    /**
     * @param  bool  $async  Live single-ping ingestion queues the write to keep
     *                       request latency low. Batch/offline-sync processes
     *                       synchronously instead: those points must be stored
     *                       (and device_status updated) before the *next* point
     *                       in the same batch is optimizer-checked, otherwise
     *                       every point in a batch would see stale "last
     *                       location" state and never get throttled correctly.
     */
    public function ingest(User $user, LocationUpdateData $dto, bool $async = true): IngestResult
    {
        $deviceSession = $this->resolveDeviceSession($user, $dto);
        $status = $deviceSession->status;

        app(LiveLocationService::class)->publish($user, $deviceSession, $dto);

        $this->recordPacketDiagnostics($user, $deviceSession, $dto, $status);

        LocationReceived::dispatch($user, $dto);
        LocationValidated::dispatch($user, $dto);

        $distanceFilterMeters = app(\App\Services\SettingsService::class)->locationSaveRadius($user);

        if ($status?->lastLocation && $dto->recordedAt->lte($status->lastLocation->recorded_at)) {
            return IngestResult::rejected($deviceSession->id, 'stale');
        }

        $decision = $this->optimizer->shouldAccept($status, $dto, $distanceFilterMeters);

        if (! $decision->accepted) {
            return IngestResult::rejected($deviceSession->id, $decision->reason);
        }

        $trackingSession = $this->resolveTrackingSession($user, $deviceSession, $status, $dto);

        if ($async) {
            StoreGpsLocationJob::dispatch($user->id, $deviceSession->id, $trackingSession->id, $dto)
                ->onConnection(config('queue.default'));
        } else {
            (new StoreGpsLocationJob($user->id, $deviceSession->id, $trackingSession->id, $dto))
                ->handle($this->optimizer);
        }

        return IngestResult::accepted($deviceSession->id, $trackingSession->id, $trackingSession->uuid);
    }

    private function resolveDeviceSession(User $user, LocationUpdateData $dto): DeviceSession
    {
        return DeviceSession::query()->firstOrCreate(
            ['user_id' => $user->id, 'session_id' => $dto->deviceId],
            [
                'device_name' => null,
                'platform' => $dto->sourceType->value,
                'is_current' => true,
                'last_login_at' => now(),
                'last_activity_at' => now(),
            ],
        );
    }

    private function resolveTrackingSession(User $user, DeviceSession $deviceSession, ?DeviceStatus $status, LocationUpdateData $dto): TrackingSession
    {
        if ($dto->trackingSessionUuid) {
            $existing = TrackingSession::query()
                ->where('uuid', $dto->trackingSessionUuid)
                ->where('device_session_id', $deviceSession->id)
                ->first();

            if ($existing && $existing->status === TrackingSessionStatus::Active) {
                return $existing;
            }
        }

        $lastPingAt = $status?->last_ping_at;
        $gapExceeded = ! $lastPingAt || $dto->recordedAt->diffInSeconds($lastPingAt, true) >= self::SESSION_GAP_SECONDS;

        $active = TrackingSession::query()
            ->where('device_session_id', $deviceSession->id)
            ->where('status', TrackingSessionStatus::Active)
            ->latest('started_at')
            ->first();

        if ($active && ! $gapExceeded) {
            return $active;
        }

        if ($active && $gapExceeded) {
            $active->update([
                'status' => TrackingSessionStatus::Ended,
                'ended_at' => $lastPingAt ?? now(),
            ]);
        }

        return TrackingSession::create([
            'user_id' => $user->id,
            'device_session_id' => $deviceSession->id,
            'source_type' => $dto->sourceType,
            'started_at' => $dto->recordedAt,
            'status' => TrackingSessionStatus::Active,
        ]);
    }

    private function recordPacketDiagnostics(User $user, DeviceSession $deviceSession, LocationUpdateData $dto, ?DeviceStatus $status): void
    {
        if ($dto->isMock) {
            $this->diagnostic($user, $deviceSession, DiagnosticEventType::MockGpsDetected, $dto);
        }

        if ($dto->accuracy !== null && $dto->accuracy > self::POOR_ACCURACY_METERS) {
            $this->diagnostic($user, $deviceSession, DiagnosticEventType::PoorAccuracy, $dto);
        }

        $this->detectImpossibleMovement($user, $deviceSession, $dto, $status);
    }

    /**
     * Physics-based spoof detector: a client can trivially lie about is_mock,
     * but it can't make two consecutive real fixes imply a physically
     * impossible speed. This is the one anti-tamper signal a browser client
     * genuinely cannot fake around.
     */
    private function detectImpossibleMovement(User $user, DeviceSession $deviceSession, LocationUpdateData $dto, ?DeviceStatus $status): void
    {
        $last = $status?->lastLocation;

        if (! $last) {
            return;
        }

        $seconds = abs($dto->recordedAt->diffInSeconds($last->recorded_at));

        if ($seconds < 1) {
            return;
        }

        $distanceMeters = $this->optimizer->distanceInMeters(
            (float) $last->latitude,
            (float) $last->longitude,
            $dto->latitude,
            $dto->longitude,
        );

        $impliedSpeedMps = $distanceMeters / $seconds;

        if ($impliedSpeedMps <= self::IMPOSSIBLE_SPEED_MPS) {
            return;
        }

        $log = DiagnosticLog::create([
            'user_id' => $user->id,
            'device_session_id' => $deviceSession->id,
            'event_type' => DiagnosticEventType::MockGpsDetected,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'reason' => sprintf(
                'Jumped %.0fm in %ds (implied %.0f km/h) — likely spoofed/mock location',
                $distanceMeters,
                $seconds,
                $impliedSpeedMps * 3.6,
            ),
            'occurred_at' => $dto->recordedAt,
        ]);

        $trackerIds = $user->trackerRelations()->where('status', UserStatus::Active)->pluck('tracker_user_id');

        User::query()->whereIn('id', $trackerIds)->get()->each(
            fn (User $tracker) => $tracker->notify(new DiagnosticAlertNotification($log, $user)),
        );
    }

    private function diagnostic(User $user, DeviceSession $deviceSession, DiagnosticEventType $type, LocationUpdateData $dto): void
    {
        DiagnosticLog::create([
            'user_id' => $user->id,
            'device_session_id' => $deviceSession->id,
            'event_type' => $type,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'occurred_at' => $dto->recordedAt,
        ]);
    }
}
