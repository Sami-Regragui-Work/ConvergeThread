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

        return [
            'name' => 'required|string|max:100|unique:tenant_roles,name,' . $tenantRoleId . ',id,tenant_id,' . Auth::user()->tenant_id,
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ];
    }
}
