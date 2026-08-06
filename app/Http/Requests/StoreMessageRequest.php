<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => 'required_without_all:file,files|nullable|string|max:5000',
            'file' => 'nullable|file|max:51200',
            'files' => 'nullable|array|max:20',
            'files.*' => 'file|max:51200',
            'parent_id' => 'nullable|exists:messages,id',
            'mention_user_ids' => 'nullable|array',
            'mention_user_ids.*' => 'integer|exists:users,id',
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
