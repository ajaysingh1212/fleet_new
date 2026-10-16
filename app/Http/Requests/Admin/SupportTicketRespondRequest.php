<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportTicketRespondRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage support tickets') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(['open', 'in_progress', 'resolved', 'closed'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
