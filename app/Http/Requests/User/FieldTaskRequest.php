<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FieldTaskRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tags' => is_string($this->tags) ? array_values(array_filter(array_map('trim', explode(',', $this->tags)))) : $this->tags,
        ]);
    }

    public function rules(): array
    {
        return [
            'assignee_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'schedule_type' => ['required', Rule::in(['hourly', 'daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly', 'custom', 'once'])],
            'starts_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after:starts_at'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'repeat_days' => ['nullable', 'array'],
            'repeat_days.*' => ['integer', 'between:0,6'],
            'arrival_radius_meters' => ['required', 'integer', 'between:25,1000'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'reference_code' => ['nullable', 'string', 'max:80'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'stops' => ['required', 'array', 'min:1', 'max:50'],
            'stops.*.title' => ['required', 'string', 'max:180'],
            'stops.*.description' => ['nullable', 'string', 'max:2000'],
            'stops.*.address' => ['nullable', 'string', 'max:500'],
            'stops.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'stops.*.expected_at' => ['nullable', 'date'],
            'stops.*.radius_meters' => ['required', 'integer', 'between:25,1000'],
        ];
    }
}
