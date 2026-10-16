<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserLicenseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'license_plan_id' => ['required', 'integer', 'exists:license_plans,id'],
        ];
    }
}
