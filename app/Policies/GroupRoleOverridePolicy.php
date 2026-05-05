<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\User;
use App\Services\GroupPermissionService;

class GroupRoleOverridePolicy
{
    public function __construct(
        private readonly GroupPermissionService $groupPermissionService
    ) {
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $viewer, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $viewer, 'group_role_overrides.manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $viewer, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $viewer, 'group_role_overrides.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $deleter, 'group_role_overrides.manage');
    }
}
