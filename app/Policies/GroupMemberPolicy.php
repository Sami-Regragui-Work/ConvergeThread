<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupPermissionService;

class GroupMemberPolicy
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
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.add');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.remove');
    }

    public function assignRole(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.assign_role');
    }
}
