<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupPermissionService;

class GroupPolicy
{
    public function __construct(
        private readonly GroupPermissionService $groupPermissionService
    ) {
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->banned_by_id === null && (string) $user->tenant_id != 0;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->banned_by_id === null && (string) $user->tenant_id != 0;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group.delete');
    }

    public function viewMembers(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.view');
    }

    public function addMember(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.add');
    }

    public function removeMember(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.remove');
    }

    public function assignMemberRole(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_members.assign_role');
    }

    public function manageRoleOverrides(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'group_role_overrides.manage');
    }

    public function viewDuos(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'duos.view');
    }

    public function createDuo(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'duos.create');
    }

    public function deleteDuo(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'duos.delete');
    }

    public function createMessage(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'messages.create');
    }
}
