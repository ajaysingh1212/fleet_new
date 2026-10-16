<?php

namespace App\Events;

use App\Http\Resources\RouteProposalResource;
use App\Models\RouteProposal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Covers propose/edit/accept/reject/cancel — the frontend just re-renders
 * the route banner and map line from the fresh payload each time.
 */
class RouteProposalUpdated implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public readonly RouteProposal $routeProposal,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->routeProposal->conversation->uuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'route-proposal.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new RouteProposalResource($this->routeProposal->loadMissing(['conversation', 'proposedBy', 'acceptedBy'])))->resolve();
    }
}
