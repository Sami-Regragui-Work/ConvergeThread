<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvitationPolicy
{
    public function createAdmin(User $owner): bool
    {
        return !$owner->is_banned && $owner->tenant_id === 0;
    }

    public function createMember(User $inviter): bool
    {
        if ($inviter->is_banned || !$inviter->tenantRole) {
            return false;
        }

        return $inviter->tenantRole->name === 'admin';
    }
}
