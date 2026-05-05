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
    public function viewAny(User $viewer): bool
    {
        return $viewer->banned_by_id === null && (string) $viewer->tenant_id != 0;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $viewer, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $viewer, 'group.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator): bool
    {
        return $creator->banned_by_id === null && (string) $creator->tenant_id != 0;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $editor, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $editor, 'group.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $deleter, 'group.delete');
    }
}
