<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupRoleOverride;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class GroupMemberService
{
    public function add(Group $group, User $member): GroupMember
    {
        $existingMembership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->first();

        if ($existingMembership) {
            $existingMembership->update([
                'group_role_override_id' => null,
                'permissions' => null,
                'left_at' => null,
            ]);

            return $existingMembership->fresh();
        }

        $group->members()->attach($member, [
            'group_role_override_id' => null,
            'permissions' => null,
            'left_at' => null,
        ]);

        return GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();
    }

    public function remove(Group $group, User $member): void
    {
        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();

        $groupMember->update(['left_at' => now()]);
    }

    public function assignRole(Group $group, User $member, ?GroupRoleOverride $roleOverride = null): GroupMember
    {
        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();

        $groupMember->update([
            'group_role_override_id' => $roleOverride?->id,
            'permissions' => $roleOverride?->permissions,
        ]);

        return $groupMember->fresh();
    }

    public function assignTenantRole(Group $group, User $member, TenantRole $tenantRole): User
    {
        if ((int) $member->tenant_id !== (int) $group->tenant_id) {
            throw ValidationException::withMessages([
                'user_id' => 'User does not belong to this workspace.',
            ]);
        }

        if (!$tenantRole->isUsableByTenant($group->tenant_id)) {
            throw ValidationException::withMessages([
                'tenant_role_id' => 'Selected role does not belong to this workspace.',
            ]);
        }

        $member->update(['tenant_role_id' => $tenantRole->id]);

        return $member->fresh(['tenantRole']);
    }

    public function getActive(Group $group): Collection
    {
        return GroupMember::where('group_id', $group->id)
            ->whereNull('left_at')
            ->get();
    }
}
