<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RouteProposalStatus;
use App\Events\RouteProposalUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\RouteProposalResource;
use App\Models\Conversation;
use App\Models\RouteProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteProposalController extends Controller
{
    private const EAGER_LOAD = ['conversation', 'proposedBy', 'acceptedBy'];

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $proposal = $conversation->routeProposals()
            ->whereIn('status', [RouteProposalStatus::Pending, RouteProposalStatus::Accepted])
            ->with(self::EAGER_LOAD)
            ->latest()
            ->first();

        return response()->json(['data' => $proposal ? new RouteProposalResource($proposal) : null]);
    }

    public function store(Request $request, Conversation $conversation): RouteProposalResource
    {
        $this->authorize('view', $conversation);

        $data = $request->validate([
            'target_lat' => ['required', 'numeric', 'between:-90,90'],
            'target_lng' => ['required', 'numeric', 'between:-180,180'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $conversation->routeProposals()
            ->whereIn('status', [RouteProposalStatus::Pending, RouteProposalStatus::Accepted])
            ->update(['status' => RouteProposalStatus::Cancelled]);

        $proposal = $conversation->routeProposals()->create([
            'proposed_by_id' => $request->user()->id,
            'target_lat' => $data['target_lat'],
            'target_lng' => $data['target_lng'],
            'label' => $data['label'] ?? null,
            'status' => RouteProposalStatus::Pending,
        ]);

        $proposal->setRelation('conversation', $conversation);
        $proposal->loadMissing(['proposedBy', 'acceptedBy']);

        broadcast(new RouteProposalUpdated($proposal));

        return new RouteProposalResource($proposal);
    }

    public function update(Request $request, RouteProposal $routeProposal): RouteProposalResource
    {
        $routeProposal->loadMissing('conversation');
        $this->authorize('view', $routeProposal->conversation);
        abort_unless($routeProposal->proposed_by_id === $request->user()->id, 403);
        abort_if($routeProposal->status === RouteProposalStatus::Cancelled, 422, 'This route proposal is no longer active.');

        $data = $request->validate([
            'target_lat' => ['required', 'numeric', 'between:-90,90'],
            'target_lng' => ['required', 'numeric', 'between:-180,180'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $routeProposal->update([
            'target_lat' => $data['target_lat'],
            'target_lng' => $data['target_lng'],
            'label' => $data['label'] ?? $routeProposal->label,
            'status' => RouteProposalStatus::Pending,
            'accepted_by_id' => null,
            'accepted_at' => null,
        ]);

        $routeProposal->loadMissing(['proposedBy', 'acceptedBy']);

        broadcast(new RouteProposalUpdated($routeProposal));

        return new RouteProposalResource($routeProposal);
    }

    public function accept(Request $request, RouteProposal $routeProposal): RouteProposalResource
    {
        $routeProposal->loadMissing('conversation');
        $this->authorize('view', $routeProposal->conversation);
        abort_if($routeProposal->proposed_by_id === $request->user()->id, 422, 'You cannot accept your own route proposal.');
        abort_unless($routeProposal->status === RouteProposalStatus::Pending, 422, 'This route proposal is no longer pending.');

        $routeProposal->update([
            'status' => RouteProposalStatus::Accepted,
            'accepted_by_id' => $request->user()->id,
            'accepted_at' => now(),
        ]);

        $routeProposal->loadMissing(['proposedBy', 'acceptedBy']);

        broadcast(new RouteProposalUpdated($routeProposal));

        return new RouteProposalResource($routeProposal);
    }

    public function reject(Request $request, RouteProposal $routeProposal): RouteProposalResource
    {
        $routeProposal->loadMissing('conversation');
        $this->authorize('view', $routeProposal->conversation);
        abort_if($routeProposal->proposed_by_id === $request->user()->id, 422, 'You cannot reject your own route proposal.');

        $routeProposal->update(['status' => RouteProposalStatus::Rejected]);

        $routeProposal->loadMissing(['proposedBy', 'acceptedBy']);

        broadcast(new RouteProposalUpdated($routeProposal));

        return new RouteProposalResource($routeProposal);
    }

    public function destroy(Request $request, RouteProposal $routeProposal): JsonResponse
    {
        $routeProposal->loadMissing('conversation');
        $this->authorize('view', $routeProposal->conversation);
        abort_unless($routeProposal->proposed_by_id === $request->user()->id, 403);

        $routeProposal->update(['status' => RouteProposalStatus::Cancelled]);
        $routeProposal->loadMissing(['proposedBy', 'acceptedBy']);

        broadcast(new RouteProposalUpdated($routeProposal));

        return response()->json(['success' => true]);
    }
}
