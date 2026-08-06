<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTenantRoleRequest extends FormRequest
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
        $tenantRoleId = $this->route('tenantRole')?->id;
        $isSystem = (bool) $this->route('tenantRole')?->is_system;

        return [
            'name' => 'required|string|max:100|unique:tenant_roles,name,' . $tenantRoleId . ',id,tenant_id,' . Auth::user()->tenant_id,
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'permissions' => ($isSystem ? 'nullable' : 'required') . '|array',
            'permissions.*' => 'string',
        ];
    }
}
