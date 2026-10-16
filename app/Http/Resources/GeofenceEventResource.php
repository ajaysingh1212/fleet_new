<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofenceEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'geofence' => [
                'uuid' => $this->geofence->uuid,
                'name' => $this->geofence->name,
                'category' => $this->geofence->category->value,
                'color' => $this->geofence->color,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
