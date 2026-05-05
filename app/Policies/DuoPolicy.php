<?php

namespace App\Policies;

use App\Models\Duo;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupPermissionService;

class DuoPolicy
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
        return $this->groupPermissionService->hasPermission($group, $viewer, 'duos.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $creator, 'duos.create');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $deleter, 'duos.delete');
    }
}
