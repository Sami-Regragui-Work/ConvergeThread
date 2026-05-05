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
    public function viewAny(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'duos.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'duos.create');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, 'duos.delete');
    }
}
