<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\Tenant;
use App\Models\TenantRole;

class RoleService
{
    public function createTenantRole(Tenant $tenant, string $name, array $permissions): TenantRole
    {
        return TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'permissions' => $permissions,
        ]);
    }

    public function createGroupRoleOverride(Group $group, TenantRole $tenantRole, ?array $permissions = null): GroupRoleOverride
    {
        return GroupRoleOverride::create([
            'group_id' => $group->id,
            'tenant_role_id' => $tenantRole->id,
            'permissions' => $permissions ?? $tenantRole->permissions,
        ]);
    }

    public function deleteTenantRole(TenantRole $tenantRole): void
    {
        $tenantRole->delete();
    }

    public function deleteGroupRoleOverride(GroupRoleOverride $groupRoleOverride): void
    {
        $groupRoleOverride->delete();
    }
}
