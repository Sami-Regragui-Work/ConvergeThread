<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\User;
use App\Services\GroupPermissionService;
use App\Support\Permissions;

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
        return $this->groupPermissionService->hasPermission($group, $viewer, Permissions::GROUP_ROLE_OVERRIDES_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $creator, Permissions::GROUP_ROLE_OVERRIDES_MANAGE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Group $group, GroupRoleOverride $groupRoleOverride): bool
    {
        if ($groupRoleOverride->group_id !== $group->id) {
            return false;
        }

        return $this->groupPermissionService->hasPermission($group, $deleter, Permissions::GROUP_ROLE_OVERRIDES_MANAGE);
    }
}
