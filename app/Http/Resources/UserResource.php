<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_id' => $this->employee_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department' => $this->department,
            'designation' => $this->designation,
            'company' => $this->company,
            'status' => $this->status?->value,
            'timezone' => $this->timezone,
            'theme' => $this->theme?->value,
            'distance_filter_meters' => app(\App\Services\SettingsService::class)->locationSaveRadius($this->resource),
        ];
    }
}
