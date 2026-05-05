<?php

namespace App\Policies;

use App\Models\User;

class InvitationPolicy
{
    public function createAdmin(User $owner): bool
    {
        return $owner->banned_by_id === null && (string) $owner->tenant_id == 1;
    }

    public function createMember(User $inviter): bool
    {
        if ($inviter->banned_by_id !== null || !$inviter->tenantRole) {
            return false;
        }

        return $inviter->tenantRole->name === 'admin';
    }
}
