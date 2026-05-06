<?php

namespace App\Policies;

use App\Models\TenantRole;
use App\Models\User;
use App\Services\TenantPermissionService;
use App\Support\Permissions;

class TenantRolePolicy
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissionService
    ) {
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $viewer): bool
    {
        return $this->tenantPermissionService->hasPermission($viewer, Permissions::TENANT_ROLES_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator): bool
    {
        return $this->tenantPermissionService->hasPermission($creator, Permissions::TENANT_ROLES_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $editor, TenantRole $tenantRole): bool
    {
        if ($editor->tenant_id !== $tenantRole->tenant_id) {
            return false;
        }

        return $this->tenantPermissionService->hasPermission($editor, Permissions::TENANT_ROLES_UPDATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, TenantRole $tenantRole): bool
    {
        if ($deleter->tenant_id !== $tenantRole->tenant_id) {
            return false;
        }

        return $this->tenantPermissionService->hasPermission($deleter, Permissions::TENANT_ROLES_DELETE);
    }
}