<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DiagnosticEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DiagnosticEventRequest;
use App\Models\DiagnosticLog;
use App\Models\User;
use App\Notifications\DiagnosticAlertNotification;
use App\Services\ActivityLogService;
use App\Services\DiagnosticService;
use App\Services\RelationshipAuthorizationService;
use Illuminate\Http\JsonResponse;

class DiagnosticController extends Controller
{
    public function __construct(
        private readonly DiagnosticService $diagnostics,
        private readonly RelationshipAuthorizationService $authorization,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * Event types worth interrupting a tracker for — the rest (browser
     * visibility changes, normal permission grants, etc.) are routine
     * telemetry, not alerts.
     */
    private const CONCERNING_EVENTS = [
        DiagnosticEventType::MockGpsDetected,
        DiagnosticEventType::PoorAccuracy,
        DiagnosticEventType::HighBatteryDrain,
        DiagnosticEventType::WebSocketLost,
        DiagnosticEventType::PermissionRevoked,
        DiagnosticEventType::ServerTimeout,
        DiagnosticEventType::AutomationDetected,
    ];

    public function store(DiagnosticEventRequest $request): JsonResponse
    {
        $eventType = DiagnosticEventType::from($request->validated('event_type'));
        $log = $this->diagnostics->record($request->user(), $request->validated());

        if (in_array($eventType, self::CONCERNING_EVENTS, true)) {
            $trackerIds = $request->user()->trackerRelations()->where('status', 'active')->pluck('tracker_user_id');

            User::query()->whereIn('id', $trackerIds)->get()->each(
                fn (User $tracker) => $tracker->notify(new DiagnosticAlertNotification($log, $request->user())),
            );
        }

        return response()->json(['message' => 'Recorded.'], 201);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($this->authorization->canViewDiagnostics(request()->user(), $user), 403);

        $this->activityLog->log(request()->user(), 'ADMIN_VIEWED_DIAGNOSTICS', $user, [
            'target_user_id' => $user->id,
        ]);

        return response()->json([
            'current_state' => $this->diagnostics->currentState($user),
            'history' => DiagnosticLog::query()
                ->where('user_id', $user->id)
                ->latest('occurred_at')
                ->limit(50)
                ->get(),
        ]);
    }
}
