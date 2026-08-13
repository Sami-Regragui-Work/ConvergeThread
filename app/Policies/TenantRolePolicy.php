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
    ) {}

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
        if (! $tenantRole->is_system && $editor->tenant_id !== $tenantRole->tenant_id) {
            return false;
        }

        return $this->tenantPermissionService->hasPermission($editor, Permissions::TENANT_ROLES_UPDATE);
    }

    public function delete(User $deleter, TenantRole $tenantRole): bool
    {
        if ($tenantRole->is_system) {
            return false;
        }

        if ($deleter->tenant_id !== $tenantRole->tenant_id) {
            return false;
        }

        return $this->tenantPermissionService->hasPermission($deleter, Permissions::TENANT_ROLES_DELETE);
    }

    /**
     * Whether the viewer may use the delete controls for roles in general.
     * Does not consider object-type restrictions (e.g. system roles), so the
     * UI can render the control disabled rather than hide it.
     */
    public function deleteAny(User $deleter): bool
    {
        return $this->tenantPermissionService->hasPermission($deleter, Permissions::TENANT_ROLES_DELETE);
    }
}
