<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackingRelationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage tracking relations') ?? false;
    }

    public function rules(): array
    {
        $relation = $this->route('trackingRelation');

        if ($relation) {
            return [
                'relationship_name' => ['required', 'string', 'max:120'],
                'status' => ['required', 'string', 'max:20'],
            ];
        }

        return [
            'tracker_user_id' => [
                'required',
                'integer',
                'exists:users,id',
                'different:tracked_user_id',
                Rule::unique('tracking_relations', 'tracker_user_id')
                    ->where('tracked_user_id', $this->input('tracked_user_id'))
                    ->whereNull('deleted_at'),
            ],
            'tracked_user_id' => ['required', 'integer', 'exists:users,id'],
            'relationship_name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', 'max:20'],
        ];
    }
}
