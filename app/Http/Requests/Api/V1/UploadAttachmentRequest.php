<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $allowedExtensions = collect(config('chat.mimes'))->flatten()->all();
        $allowedLabel = implode(', ', $allowedExtensions);

        return [
            'file' => [
                'required',
                'file',
                'max:'.config('chat.max_upload_kb'),
                function (string $attribute, mixed $value, \Closure $fail) use ($allowedExtensions, $allowedLabel): void {
                    $extension = strtolower($value?->getClientOriginalExtension() ?? '');

                    if (! in_array($extension, $allowedExtensions, true)) {
                        $fail("The {$attribute} field must be a file of type: {$allowedLabel}.");
                    }
                },
            ],
            'caption' => ['nullable', 'string', 'max:2000'],
            'view_once' => ['sometimes', 'boolean'],
            'reply_to_message_id' => ['nullable', 'uuid', 'exists:messages,uuid'],
        ];
    }
}
