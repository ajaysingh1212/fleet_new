<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage notification templates') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', Rule::in(['mail', 'database', 'sms'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
