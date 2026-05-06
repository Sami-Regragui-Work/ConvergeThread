<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupPermissionService;
use App\Support\Permissions;

class GroupPolicy
{
    public function __construct(
        private readonly GroupPermissionService $groupPermissionService
    ) {
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $viewer): bool
    {
        return $viewer->banned_by_id === null && !$viewer->isOwner();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $viewer, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $viewer, Permissions::GROUP_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator): bool
    {
        return $creator->banned_by_id === null && !$creator->isOwner();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $editor, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $editor, Permissions::GROUP_UPDATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $deleter, Permissions::GROUP_DELETE);
    }

    public function invite(User $inviter, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $inviter, Permissions::GROUP_INVITE);
    }
}
