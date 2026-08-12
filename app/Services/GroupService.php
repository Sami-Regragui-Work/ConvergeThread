<?php

namespace App\Services;

use App\Models\Group;
use App\Models\TenantRole;
use App\Models\User;
use App\Support\WorkspaceSync;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class GroupService
{
    public function __construct(private readonly GroupMemberService $groupMemberService) {}

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

            $this->groupMemberService->add($group, $creator);

            WorkspaceSync::bump($creator->tenant_id, ['groups', 'members']);

            return $group;
        });
    }

    public function update(Group $group, array $attributes): Group
    {
        $updates = array_intersect_key($attributes, array_flip(['name', 'accent_color']));

        if ($updates) {
            $group->update($updates);
            WorkspaceSync::bump($group->tenant_id, ['groups']);
        }

        return $group->fresh();
    }

    public function delete(Group $group): void
    {
        $tenantId = $group->tenant_id;
        $group->delete();
        WorkspaceSync::bump($tenantId, ['groups', 'members']);
    }

    public function getIndexDataForUser(User $user, string $sort = 'created_at', string $dir = 'desc'): array
    {
        $groups = Group::where('tenant_id', $user->tenant_id)
            ->withCount(['activeMembers'])
            ->with('creator:id,display_name')
            ->orderBy($sort, $dir)
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

        $tenantRoles = TenantRole::assignableForInviter($user);

        return compact('groups', 'memberGroupIds', 'managerGroupIds', 'tenantRoles');
    }
}
