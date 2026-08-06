<?php

namespace App\Services;

use App\Models\GroupMember;
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

        if ($this->hasPermissionFromTenantRole($user, $permission)) {
            return true;
        }

        return $this->hasPermissionViaGroupOverrides($user, $permission);
    }

    public function canViewWorkspaceMembers(User $user): bool
    {
        return $this->hasPermission($user, Permissions::WORKSPACE_MEMBERS_VIEW);
    }

    public function canManageWorkspaceMembers(User $user): bool
    {
        return $this->hasPermission($user, Permissions::INVITATIONS_CREATE_MEMBER);
    }

    private function hasPermissionFromTenantRole(User $user, string $permission): bool
    {
        if (!$user->tenantRole) {
            return false;
        }

        $permissions = Permissions::expand($user->tenantRole->permissions ?? []);

        return in_array($permission, $permissions, true);
    }

    /**
     * Group role overrides can extend tenant-level access.
     */
    private function hasPermissionViaGroupOverrides(User $user, string $permission): bool
    {
        $memberships = GroupMember::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->with('groupRoleOverride')
            ->get();

        foreach ($memberships as $membership) {
            $grants = $membership->groupRoleOverride?->permissions ?? [];

            if ($grants === []) {
                continue;
            }

            if (in_array($permission, Permissions::expand($grants), true)) {
                return true;
            }
        }

        return false;
    }
}
