<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupPermissionService;
use App\Support\Permissions;

class GroupMemberPolicy
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
        return $this->groupPermissionService->hasPermission($group, $viewer, Permissions::GROUP_MEMBERS_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $creator, Permissions::GROUP_MEMBERS_ADD);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $deleter, Permissions::GROUP_MEMBERS_REMOVE);
    }

    public function assignRole(User $editor, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $editor, Permissions::GROUP_MEMBERS_ASSIGN_ROLE);
    }
}