<?php

namespace App\Services;

use App\Models\User;
use App\Support\Permissions;

class TenantPermissionService
{
    public function hasPermission(User $user, string $permission): bool
    {
        if ($user->banned_by_id !== null) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        if (!$user->tenantRole) {
            return false;
        }

        $permissions = Permissions::expand($user->tenantRole->permissions ?? []);

        return in_array($permission, $permissions, true);
    }
}
