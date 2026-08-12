<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupPermissionService;
use App\Services\RoleHierarchyService;
use App\Support\Permissions;

class GroupMemberPolicy
{
    public function __construct(
        private readonly GroupPermissionService $groupPermissionService
    ) {}

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
    public function delete(User $deleter, GroupMember $member, Group $group): bool
    {
        $target = $member->user;

        if (! $target) {
            return false;
        }

        if ((int) $deleter->id === (int) $target->id) {
            return false;
        }

        // The group creator cannot be removed from their own group.
        if ((int) $group->creator_id === (int) $target->id) {
            return false;
        }

        // The group creator may manage anyone in their own group.
        $isCreator = (int) $group->creator_id === (int) $deleter->id;
        if ($isCreator) {
            return true;
        }

        if (! $this->groupPermissionService->hasPermission($group, $deleter, Permissions::GROUP_MEMBERS_REMOVE)) {
            return false;
        }

        // Respect the role hierarchy: you cannot remove someone
        // at your own level or above.
        return app(RoleHierarchyService::class)->canManageUser($deleter, $target);
    }

    public function assignRole(User $editor, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $editor, Permissions::GROUP_MEMBERS_ASSIGN_ROLE);
    }

    public function assignTenantRole(User $editor, Group $group): bool
    {
        return $this->assignRole($editor, $group)
            || $this->groupPermissionService->hasPermission($group, $editor, Permissions::GROUP_INVITE);
    }
}
