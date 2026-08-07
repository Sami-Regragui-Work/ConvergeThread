<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => 'sometimes|nullable|string|max:20000',
            'empty_content' => 'sometimes|boolean',
            'files' => 'nullable|array|max:20',
            'files.*' => 'file|max:51200',
            'file' => 'nullable|file|max:51200',
            'remove_attachment_ids' => 'nullable|array',
            'remove_attachment_ids.*' => 'integer|exists:message_attachments,id',
            'remove_file' => 'sometimes|boolean',
            'attachment_meta' => 'nullable|array',
            'attachment_meta.*.name' => 'nullable|string|max:255',
            'attachment_meta.*.mime' => 'nullable|string|max:127',
            'attachment_meta.*.iv' => 'nullable|string|max:64',
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.max' => 'Each file must be 50 MB or smaller.',
            'file.max' => 'Each file must be 50 MB or smaller.',
            'files.max' => 'You can attach at most 20 files per message.',
        ];
    }
}
