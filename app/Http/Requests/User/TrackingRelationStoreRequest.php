<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackingRelationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tracked_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->user()->id]),
                Rule::unique('tracking_relations', 'tracked_user_id')
                    ->where('tracker_user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'relationship_name' => ['required', 'string', 'max:120'],
        ];
    }
}