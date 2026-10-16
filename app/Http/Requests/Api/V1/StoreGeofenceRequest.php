<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\GeofenceCategory;
use App\Enums\GeofenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGeofenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(GeofenceType::class)],
            'category' => ['required', Rule::enum(GeofenceCategory::class)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'center_lat' => ['required_if:type,circle', 'nullable', 'numeric', 'between:-90,90'],
            'center_lng' => ['required_if:type,circle', 'nullable', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required_if:type,circle', 'nullable', 'integer', 'min:1', 'max:200000'],
            'points' => ['required_if:type,polygon,rectangle', 'array', 'min:3'],
            'points.*.latitude' => ['required_with:points', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['required_with:points', 'numeric', 'between:-180,180'],
            'min_speed_kmh' => ['nullable', 'integer', 'min:0', 'max:300'],
            'max_speed_kmh' => [
                'nullable', 'integer', 'min:0', 'max:300',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $this->filled('min_speed_kmh') && $value < (int) $this->input('min_speed_kmh')) {
                        $fail('Max speed must be greater than or equal to min speed.');
                    }
                },
            ],
        ];
    }
}
