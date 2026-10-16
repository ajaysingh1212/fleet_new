<?php

namespace App\Http\Controllers\Api\V1;

use App\DTO\LocationUpdateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LocationBatchUpdateRequest;
use App\Http\Requests\Api\V1\LocationUpdateRequest;
use App\Models\OfflineSyncLog;
use App\Services\Gps\GpsIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class GpsLocationController extends Controller
{
    public function __construct(
        protected GpsIngestionService $ingestionService,
    ) {}

    public function store(LocationUpdateRequest $request): JsonResponse
    {
        $result = $this->ingestionService->ingest($request->user(), LocationUpdateData::fromRequest($request));

        if (! $result->accepted) {
            return response()->json(['accepted' => false, 'reason' => $result->reason]);
        }

        return response()->json([
            'accepted' => true,
            'tracking_session_id' => $result->trackingSessionUuid,
        ], 202);
    }

    public function batchStore(LocationBatchUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $fallbackDeviceId = $request->hasSession() ? $request->session()->getId() : null;
        $packets = $request->validated('locations');

        $acceptedCount = 0;
        $rejectedCount = 0;
        $oldest = null;
        $newest = null;
        $deviceSessionId = null;

        foreach ($packets as $packet) {
            $packet['device_id'] ??= $fallbackDeviceId;
            $dto = LocationUpdateData::fromArray($packet);

            $oldest = $oldest === null || $dto->recordedAt->lt($oldest) ? $dto->recordedAt : $oldest;
            $newest = $newest === null || $dto->recordedAt->gt($newest) ? $dto->recordedAt : $newest;

            $result = $this->ingestionService->ingest($user, $dto, async: false);
            $deviceSessionId = $result->deviceSessionId;

            $result->accepted ? $acceptedCount++ : $rejectedCount++;
        }

        $syncLog = OfflineSyncLog::create([
            'user_id' => $user->id,
            'device_session_id' => $deviceSessionId,
            'batch_size' => count($packets),
            'oldest_recorded_at' => $oldest ?? CarbonImmutable::now(),
            'newest_recorded_at' => $newest ?? CarbonImmutable::now(),
            'accepted_count' => $acceptedCount,
            'rejected_count' => $rejectedCount,
            'synced_at' => now(),
        ]);

        return response()->json([
            'accepted_count' => $acceptedCount,
            'rejected_count' => $rejectedCount,
            'sync_log_uuid' => $syncLog->uuid,
        ], 202);
    }
}
