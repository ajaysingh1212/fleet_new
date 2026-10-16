<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\GeofenceScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeofenceAssignmentRequest extends FormRequest
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
            'route_label' => ['nullable', 'string', 'max:255'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'schedule_type' => ['required', Rule::enum(GeofenceScheduleType::class)],
            'schedule_days' => ['required_if:schedule_type,weekly,monthly,quarterly,yearly', 'nullable', 'array', 'min:1'],
            'schedule_days.*' => ['required'],
            'schedule_date' => ['required_if:schedule_type,custom_date', 'nullable', 'date'],
            'window_start' => ['nullable', 'date_format:H:i'],
            'window_end' => ['nullable', 'date_format:H:i', 'after:window_start'],
            'alert_on_exit' => ['nullable', 'boolean'],
            'alert_on_missed' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }
}
