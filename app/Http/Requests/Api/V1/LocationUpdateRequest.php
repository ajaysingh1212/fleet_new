<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\SourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return static::packetRules();
    }

    /**
     * Shared with LocationBatchUpdateRequest so both validate packets identically.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function packetRules(string $prefix = ''): array
    {
        return [
            $prefix.'device_id' => ['nullable', 'string', 'max:191'],
            $prefix.'source_type' => ['required', Rule::enum(SourceType::class)],
            $prefix.'latitude' => ['required', 'numeric', 'between:-90,90'],
            $prefix.'longitude' => ['required', 'numeric', 'between:-180,180'],
            $prefix.'accuracy' => ['nullable', 'numeric', 'min:0'],
            $prefix.'speed' => ['nullable', 'numeric', 'min:0'],
            $prefix.'bearing' => ['nullable', 'numeric', 'between:0,360'],
            $prefix.'heading' => ['nullable', 'numeric', 'between:0,360'],
            $prefix.'altitude' => ['nullable', 'numeric'],
            $prefix.'battery_level' => ['nullable', 'integer', 'between:0,100'],
            $prefix.'network_type' => ['nullable', 'string', 'max:20'],
            $prefix.'signal_strength' => ['nullable', 'integer', 'between:0,100'],
            $prefix.'provider' => ['nullable', 'string', 'max:30'],
            $prefix.'is_mock' => ['nullable', 'boolean'],
            $prefix.'recorded_at' => ['required', 'date'],
            $prefix.'tracking_session_id' => ['nullable', 'uuid'],
        ];
    }
}
