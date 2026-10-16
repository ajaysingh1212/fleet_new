<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class TrackingFriendRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'relationship_name' => ['required', 'string', 'max:120'],
        ];
    }
}
