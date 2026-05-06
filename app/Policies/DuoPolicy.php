<?php

namespace App\Policies;

use App\Models\Duo;
use App\Models\Group;
use App\Models\User;
use App\Services\ChatablePermissionService;
use App\Support\Permissions;

class DuoPolicy
{
    public function __construct(
        private readonly ChatablePermissionService $chatablePermissionService
    ) {
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $viewer, Group $group): bool
    {
        return $this->chatablePermissionService->hasPermission($group, $viewer, Permissions::DUOS_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator, Group $group): bool
    {
        return $this->chatablePermissionService->hasPermission($group, $creator, Permissions::DUOS_CREATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Group $group, Duo $duo): bool
    {
        if ($duo->group_id !== $group->id) {
            return false;
        }

        return $this->chatablePermissionService->hasPermission($duo, $deleter, Permissions::DUOS_DELETE);
    }
}