<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public function create(string $name, User $creator): Group
    {
        if ($creator->isOwner()) {
            throw new AuthorizationException('Owners cannot create tenant groups.');
        }

        return DB::transaction(function () use ($name, $creator) {
            $group = Group::create([
                'tenant_id' => $creator->tenant_id,
                'name' => $name,
                'creator_id' => $creator->id,
            ]);

            return $group;
        });
    }

    public function joinGroup(Group $group, User $user): GroupMember
    {
        if ($user->tenant_id !== $group->tenant_id) {
            throw new AuthorizationException('You cannot join a group outside your tenant.');
        }

        $already = $group->activeMembers()->where('users.id', $user->id)->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'group' => 'You are already a member of this group.',
            ]);
        }

        $group->members()->attach($user->id, [
            'tenant_role_id' => $user->tenant_role_id,
            'group_role_override_id' => null,
            'permissions' => null,
            'left_at' => null,
        ]);

        return $group->members()->where('users.id', $user->id)->first()->pivot;
    }

    public function updateName(Group $group, string $name): Group
    {
        $group->update(['name' => $name]);

        return $group->fresh();
    }

    public function delete(Group $group): void
    {
        $group->delete();
    }

    public function getIndexDataForUser(User $user): array
    {
        $groups = Group::where('tenant_id', $user->tenant_id)
            ->withCount(['activeMembers'])
            ->with('creator:id,display_name')
            ->get();

        $memberGroupIds = $user->groups()->pluck('group_members.group_id');

        $isAdminOrMod = $user->tenantRole && in_array(
            $user->tenantRole->name,
            ['Admin', 'Moderator'],
            true
        );

        $managerGroupIds = $isAdminOrMod
            ? $groups->pluck('id')
            : $groups->where('creator_id', $user->id)->pluck('id');

        return compact('groups', 'memberGroupIds', 'managerGroupIds');
    }
}
