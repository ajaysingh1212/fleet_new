<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LicensePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type?->value,
            'duration_in_days' => $this->duration_in_days,
            'price' => $this->price,
            'renewal_price' => $this->renewal_price,
            'is_free' => $this->is_free,
            'status' => $this->status?->value ?? $this->status,
            'description' => $this->description,
        ];
    }
}
