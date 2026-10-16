<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignUserLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage user licenses') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'license_plan_id' => ['required', 'integer', 'exists:license_plans,id'],
        ];
    }
}
