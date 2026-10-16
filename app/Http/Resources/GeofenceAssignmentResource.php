<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeofenceAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'geofence' => $this->whenLoaded('geofence', fn () => [
                'uuid' => $this->geofence->uuid,
                'name' => $this->geofence->name,
                'type' => $this->geofence->type->value,
                'category' => $this->geofence->category->value,
                'color' => $this->geofence->color,
                'center_lat' => $this->geofence->center_lat,
                'center_lng' => $this->geofence->center_lng,
                'radius_meters' => $this->geofence->radius_meters,
                'points' => $this->geofence->relationLoaded('points') ? $this->geofence->points->map(fn ($point) => [
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                ]) : [],
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'assigned_by' => $this->whenLoaded('assignedByUser', fn () => $this->assignedByUser ? [
                'id' => $this->assignedByUser->id,
                'name' => $this->assignedByUser->name,
            ] : null),
            'route_label' => $this->route_label,
            'sequence' => $this->sequence,
            'schedule_type' => $this->schedule_type->value,
            'schedule_days' => $this->schedule_days,
            'schedule_date' => $this->schedule_date?->toDateString(),
            'window_start' => $this->window_start,
            'window_end' => $this->window_end,
            'alert_on_exit' => $this->alert_on_exit,
            'alert_on_missed' => $this->alert_on_missed,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
