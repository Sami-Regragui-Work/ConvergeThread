<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Support\Permissions;

class GroupPermissionService
{
    public function getMembership(Group $group, User $member): ?GroupMember
    {
        return GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->with('groupRoleOverride')
            ->first();
    }

    public function isActiveMember(Group $group, User $user): bool
    {
        return $this->getMembership($group, $user) !== null;
    }

    public function getDirectPermissions(Group $group, User $user): array
    {
        if ($user->banned_by_id !== null || $user->tenant_id !== $group->tenant_id) {
            return [];
        }

        $membership = $this->getMembership($group, $user);

        $tenantPermissions = $user->tenantRole?->permissions ?? [];
        $groupRolePermissions = $membership?->groupRoleOverride?->permissions ?? [];
        $membershipPermissions = $membership?->permissions ?? [];

        $directPermissions = [
            ...$tenantPermissions,
            ...$groupRolePermissions,
            ...$membershipPermissions,
        ];

        if ($membership) {
            $directPermissions = [
                ...$directPermissions,
                ...Permissions::memberDefaults(),
            ];
        }

        if ($group->creator_id === $user->id) {
            $directPermissions = [
                ...$directPermissions,
                Permissions::GROUP_UPDATE,
                Permissions::GROUP_DELETE,
            ];
        }

        return array_values(array_unique($directPermissions));
    }

    public function getEffectivePermissions(Group $group, User $user): array
    {
        return Permissions::expand($this->getDirectPermissions($group, $user));
    }

    public function hasPermission(?Group $group, User $user, string $permission): bool
{
    if ($user->banned_by_id !== null) {
        return false;
    }

    if ($group !== null && $user->tenant_id !== $group->tenant_id) {
        return false;
    }

    if ($group === null) {
        $permissions = Permissions::expand($user->tenantRole?->permissions ?? []);

        return in_array($permission, $permissions, true);
    }

    $permissions = $this->getEffectivePermissions($group, $user);

    if (!in_array($permission, $permissions, true)) {
        return false;
    }

    if (!Permissions::requiresGroupMembership($permission)) {
        return true;
    }

    return $this->isActiveMember($group, $user);
    }
}
