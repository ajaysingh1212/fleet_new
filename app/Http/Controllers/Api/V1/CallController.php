<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CallType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitiateCallRequest;
use App\Models\CallSession;
use App\Models\Conversation;
use App\Services\CallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(
        protected CallService $callService,
    ) {}

    public function iceServers(): JsonResponse
    {
        return response()->json(['data' => config('webrtc.ice_servers')]);
    }

    public function store(InitiateCallRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('create', [CallSession::class, $conversation]);

        $call = $this->callService->initiate($conversation, $request->user(), $request->enum('type', CallType::class));

        return response()->json(['data' => $this->payload($call)], 201);
    }

    public function index(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $calls = $conversation->callSessions()
            ->with(['initiator', 'participants.user'])
            ->latest()
            ->get()
            ->map(fn (CallSession $call) => $this->payload($call));

        return response()->json(['data' => $calls->values()]);
    }

    public function accept(CallSession $call): JsonResponse
    {
        $this->authorize('join', $call);

        $this->callService->accept($call, request()->user());

        // Returning the up-to-date participant list lets the accepting
        // client start connecting to everyone already in the room right
        // away, instead of racing the `.call.accepted` broadcast round-trip.
        return response()->json(['data' => $this->payload($call->fresh())]);
    }

    public function reject(CallSession $call): JsonResponse
    {
        $this->authorize('view', $call);

        $this->callService->reject($call, request()->user());

        return response()->json(['success' => true]);
    }

    public function leave(CallSession $call): JsonResponse
    {
        $this->authorize('view', $call);

        $this->callService->leave($call, request()->user());

        return response()->json(['success' => true]);
    }

    public function missed(CallSession $call): JsonResponse
    {
        $this->authorize('view', $call);

        $this->callService->markMissed($call);

        return response()->json(['success' => true]);
    }

    public function update(Request $request, CallSession $call): JsonResponse
    {
        $this->authorize('view', $call);

        $this->callService->updateParticipantState($call, $request->user(), $request->only(['is_muted', 'is_video_enabled', 'is_on_hold']));

        return response()->json(['success' => true]);
    }

    private function payload(CallSession $call): array
    {
        $call->loadMissing(['initiator', 'conversation', 'participants.user']);

        return [
            'uuid' => $call->uuid,
            'conversation_uuid' => $call->conversation->uuid,
            'conversation_type' => $call->conversation->type->value,
            'type' => $call->type->value,
            'status' => $call->status->value,
            'initiator' => [
                'id' => $call->initiator->id,
                'name' => $call->initiator->name,
            ],
            'started_at' => $call->started_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
            'ended_reason' => $call->ended_reason,
            'participants' => $call->participants->map(fn ($participant) => [
                'user_id' => $participant->user_id,
                'name' => $participant->user->name,
                'status' => $participant->status->value,
                'is_on_hold' => $participant->is_on_hold,
            ]),
        ];
    }
}
