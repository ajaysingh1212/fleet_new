<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage countries') ?? false;
    }

    public function rules(): array
    {
        $countryId = $this->route('country')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'iso2' => ['required', 'string', 'size:2', Rule::unique('countries', 'iso2')->ignore($countryId)],
            'iso3' => ['nullable', 'string', 'size:3', Rule::unique('countries', 'iso3')->ignore($countryId)],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
