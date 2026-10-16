<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage users') ?? false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isUpdate = $userId !== null;

        return [
            'employee_id' => ['required', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($userId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => [$isUpdate ? 'nullable' : 'required', Password::defaults()],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'gender' => ['nullable', 'string', 'max:20'],
            'dob' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'language_id' => ['nullable', 'integer', 'exists:languages,id'],
            'status' => ['required', new Enum(UserStatus::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
