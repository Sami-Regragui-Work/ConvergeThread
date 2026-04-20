<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupRoleOverride;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GroupMemberService
{
    public function add(Group $group, User $member): GroupMember
    {
        $group->members()->detach($member);

        $group->members()->attach($member, [
            'group_role_override_id' => null,
            'permissions' => null,
        ]);

        return $group->members()
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->first();
    }

    public function remove(Group $group, User $member): void
    {
        $groupMember = $group->members()
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();

        $groupMember->update(['left_at' => now()]);
    }

    public function assignRole(Group $group, User $member, ?GroupRoleOverride $roleOverride = null): GroupMember
    {
        $groupMember = $group->members()
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();

        $groupMember->update([
            'group_role_override_id' => $roleOverride?->id,
            'permissions' => $roleOverride?->permissions,
        ]);

        return $groupMember->fresh();
    }

    public function getActive(Group $group): Collection
    {
        return $group->activeMembers()
            ->with(['groupRoleOverride.tenantRole'])
            ->get();
    }
}
