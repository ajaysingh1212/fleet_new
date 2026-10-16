<?php

namespace App\Http\Requests;

use App\Enums\ThemeMode;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique(User::class)->ignore($this->user()->id)],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'theme' => ['nullable', new Enum(ThemeMode::class)],
            'address' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }
}
