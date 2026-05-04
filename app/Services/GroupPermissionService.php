<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

class GroupPermissionService
{
    public function getMembership(Group $group, User $member): ?GroupMember
    {
        return GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->first();
    }

    public function getEffectivePermissions(Group $group, User $member): array
    {
        if ($member->banned_by_id !== null || (string) $member->tenant_id != (string) $group->tenant_id) {
            return [];
        }

        if ((string) $group->creator_id == (string) $member->id) {
            return ['*'];
        }

        $membership = $this->getMembership($group, $member);

        if (!$membership) {
            return [];
        }

        $basePermissions = $member->tenantRole?->permissions ?? [];
        $groupPermissions = $membership->permissions ?? [];

        return array_values(array_unique([
            ...$basePermissions,
            ...$groupPermissions,
        ]));
    }

    public function hasPermission(Group $group, User $member, string $permission): bool
    {
        $permissions = $this->getEffectivePermissions($group, $member);

        return in_array('*', $permissions) || in_array($permission, $permissions);
    }
}
