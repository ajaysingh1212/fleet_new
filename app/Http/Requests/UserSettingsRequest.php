<?php

namespace App\Http\Requests;

use App\Enums\ThemeMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UserSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'theme' => ['required', new Enum(ThemeMode::class)],
            'timezone' => ['required', 'string', 'max:100'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
        ];
    }
}
