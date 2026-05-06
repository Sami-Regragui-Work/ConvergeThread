<?php

namespace App\Policies;

use App\Models\User;
use App\Services\TenantPermissionService;
use App\Support\Permissions;

class InvitationPolicy
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissionService
    ) {
    }

    /**
     * Determine whether the user can create admin invitations.
     */
    public function createAdmin(User $owner): bool
    {
        return $owner->banned_by_id === null && $owner->isOwner();
    }

    /**
     * Determine whether the user can create member invitations.
     */
    public function createMember(User $inviter): bool
    {
        return $this->tenantPermissionService->hasPermission(
            $inviter,
            Permissions::INVITATIONS_CREATE_MEMBER
        );
    }
}