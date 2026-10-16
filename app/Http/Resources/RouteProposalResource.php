<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'conversation_uuid' => $this->conversation->uuid,
            'proposed_by' => [
                'id' => $this->proposedBy->id,
                'name' => $this->proposedBy->name,
            ],
            'accepted_by' => $this->whenLoaded('acceptedBy', fn () => $this->acceptedBy ? [
                'id' => $this->acceptedBy->id,
                'name' => $this->acceptedBy->name,
            ] : null),
            'target_lat' => $this->target_lat,
            'target_lng' => $this->target_lng,
            'label' => $this->label,
            'status' => $this->status->value,
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
