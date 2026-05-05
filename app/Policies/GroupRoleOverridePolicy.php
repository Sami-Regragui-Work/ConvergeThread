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
    public function viewAny(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_role_overrides.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Group $group): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_role_overrides.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Group $group): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_role_overrides.manage');
    }
}
