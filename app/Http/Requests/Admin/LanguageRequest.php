<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage languages') ?? false;
    }

    public function rules(): array
    {
        $languageId = $this->route('language')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['required', 'string', 'max:10', Rule::unique('languages', 'locale')->ignore($languageId)],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
