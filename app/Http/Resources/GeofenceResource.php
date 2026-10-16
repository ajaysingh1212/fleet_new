<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'color' => $this->color,
            'status' => $this->status->value,
            'center_lat' => $this->center_lat,
            'center_lng' => $this->center_lng,
            'radius_meters' => $this->radius_meters,
            'min_speed_kmh' => $this->min_speed_kmh,
            'max_speed_kmh' => $this->max_speed_kmh,
            'points' => $this->whenLoaded('points', fn () => $this->points->map(fn ($point) => [
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
            ])),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
