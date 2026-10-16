<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ManagedTrackedUserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'relationship_name' => ['required', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
