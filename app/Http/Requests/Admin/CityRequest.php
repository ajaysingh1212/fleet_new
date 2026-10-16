<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage cities') ?? false;
    }

    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
