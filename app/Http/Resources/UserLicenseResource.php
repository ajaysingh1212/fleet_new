<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserLicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'license_number' => $this->license_number,
            'plan' => new LicensePlanResource($this->whenLoaded('plan')),
            'assigned_tracked_user_id' => $this->assigned_tracked_user_id,
            'usage_type' => $this->usage_type,
            'is_free_claim' => $this->is_free_claim,
            'purchase_date' => $this->purchase_date,
            'activation_date' => $this->activation_date,
            'expiry_date' => $this->expiry_date,
            'status' => $this->status?->value,
            'payment_status' => $this->payment_status?->value,
            'invoice_number' => $this->invoice_number,
            'order_number' => $this->order_number,
        ];
    }
}
