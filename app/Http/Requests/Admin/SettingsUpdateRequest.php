<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', Rule::in(['site', 'system', 'license', 'security', 'appearance', 'payments'])],
            'values' => ['nullable', 'array'],
            'values.location_save_radius_meters' => ['sometimes', 'required', 'integer', 'between:1,65535'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
        ];
    }
}
