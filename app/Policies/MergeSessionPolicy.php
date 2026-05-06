<?php

namespace App\Policies;

use App\Models\MergeSession;
use App\Models\User;
use App\Services\ChatablePermissionService;
use App\Support\Permissions;

class MergeSessionPolicy
{
    public function __construct(
        private readonly ChatablePermissionService $chatablePermissionService
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
    public function view(User $viewer, MergeSession $mergeSession): bool
    {
        return $this->chatablePermissionService->hasPermission($mergeSession, $viewer, Permissions::MERGE_SESSIONS_VIEW);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator): bool
    {
        return $creator->banned_by_id === null && !$creator->isOwner();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $ender, MergeSession $mergeSession): bool
    {
        return $this->chatablePermissionService->hasPermission($mergeSession, $ender, Permissions::MERGE_SESSIONS_DELETE);
    }
}