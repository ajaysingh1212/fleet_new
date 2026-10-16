<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage states') ?? false;
    }

    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:25'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
