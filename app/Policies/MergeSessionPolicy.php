<?php

namespace App\Policies;

use App\Models\MergeSession;
use App\Models\User;
use App\Services\ChatablePermissionService;
use App\Services\TenantPermissionService;
use App\Support\Permissions;

class MergeSessionPolicy
{
    public function __construct(
        private readonly ChatablePermissionService $chatablePermissionService,
        private readonly TenantPermissionService $tenantPermissionService,
    ) {
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $viewer): bool
    {
        return $this->tenantPermissionService->hasPermission($viewer, Permissions::MERGE_SESSIONS_VIEW);
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
        return $this->tenantPermissionService->hasPermission($creator, Permissions::MERGE_SESSIONS_CREATE);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $ender, MergeSession $mergeSession): bool
    {
        return $this->chatablePermissionService->hasPermission($mergeSession, $ender, Permissions::MERGE_SESSIONS_DELETE);
    }
}