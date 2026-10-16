<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CallType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CallType::class)],
        ];
    }
}
