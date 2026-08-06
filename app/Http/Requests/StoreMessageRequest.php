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
            'file' => 'nullable|file|max:10240',
            'files' => 'nullable|array',
            'files.*' => 'file|max:10240',
            'parent_id' => 'nullable|exists:messages,id',
            'mention_user_ids' => 'nullable|array',
            'mention_user_ids.*' => 'integer|exists:users,id',
        ];
    }
}
