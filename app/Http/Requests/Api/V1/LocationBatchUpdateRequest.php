<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class LocationBatchUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'locations' => ['required', 'array', 'min:1', 'max:500'],
        ];

        foreach (LocationUpdateRequest::packetRules('locations.*.') as $field => $fieldRules) {
            $rules[$field] = $fieldRules;
        }

        return $rules;
    }
}
