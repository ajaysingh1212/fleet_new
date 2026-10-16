<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofenceAssignmentRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'run_date' => $this->run_date->toDateString(),
            'status' => $this->status->value,
            'visited_at' => $this->visited_at?->toIso8601String(),
            'notified_at' => $this->notified_at?->toIso8601String(),
        ];
    }
}
