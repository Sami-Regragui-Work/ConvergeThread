<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\Tenant;
use App\Models\TenantRole;
use Illuminate\Validation\ValidationException;

class RoleService
{
    public function createTenantRole(Tenant $tenant, string $name, array $permissions): TenantRole
    {
        return TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'permissions' => $permissions,
            'is_system' => false,
        ]);
    }

    public function createGroupRoleOverride(Group $group, TenantRole $tenantRole, ?array $permissions = null): GroupRoleOverride
    {
        if (!$tenantRole->is_system && (int) $tenantRole->tenant_id !== (int) $group->tenant_id) {
            throw ValidationException::withMessages([
                'tenant_role_id' => 'Selected role does not belong to this tenant.',
            ]);
        }

        return GroupRoleOverride::create([
            'group_id' => $group->id,
            'tenant_role_id' => $tenantRole->id,
            'permissions' => $permissions ?? $tenantRole->permissions,
        ]);
    }

    public function deleteTenantRole(TenantRole $tenantRole): void
    {
        if ($tenantRole->is_system) {
            throw ValidationException::withMessages([
                'tenant_role' => 'System roles cannot be deleted.',
            ]);
        }

        $tenantRole->delete();
    }

    public function deleteGroupRoleOverride(GroupRoleOverride $groupRoleOverride): void
    {
        $groupRoleOverride->delete();
    }
}