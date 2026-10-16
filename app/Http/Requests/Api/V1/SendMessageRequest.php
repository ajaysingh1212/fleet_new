<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
            'metadata' => ['nullable', 'array'],
            'metadata.kind' => ['nullable', 'string', 'in:live_location,current_location'],
            'metadata.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'metadata.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'metadata.duration_minutes' => ['nullable', 'integer', 'min:1'],
            'metadata.expires_at' => ['nullable', 'date'],
            'metadata.cancelled_at' => ['nullable', 'date'],
            'reply_to_message_id' => ['nullable', 'uuid', 'exists:messages,uuid'],
            'forwarded_from_message_id' => ['nullable', 'uuid', 'exists:messages,uuid'],
        ];
    }
}
