<?php

namespace App\Policies;

use App\Models\MergeSession;
use App\Models\User;
use App\Services\GroupPermissionService;
use Illuminate\Auth\Access\Response;

class MergeSessionPolicy
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
        return $viewer->banned_by_id === null && $viewer->tenant_id != 0;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $viewer, MergeSession $mergeSession): bool
    {
        foreach ($mergeSession->groups as $group) {
            if ($this->groupPermissionService->hasPermission($group, $viewer, 'merge_sessions.view')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator): bool
    {
        return $creator->banned_by_id === null && $creator->tenant_id != 0;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $ender, MergeSession $mergeSession): bool
    {
        foreach ($mergeSession->groups as $group) {
            if (!$this->groupPermissionService->hasPermission($group, $ender, 'merge_sessions.delete')) {
                return false;
            }
        }

        return $mergeSession->groups->isNotEmpty();
    }
}
