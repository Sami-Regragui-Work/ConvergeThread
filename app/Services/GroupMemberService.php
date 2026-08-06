<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupRoleOverride;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\AddedToGroupNotification;
use App\Notifications\GroupPermissionsChangedNotification;
use App\Notifications\RoleChangedNotification;
use App\Support\WorkspaceSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class GroupMemberService
{
    public function add(Group $group, User $member, ?User $addedBy = null): GroupMember
    {
        $existingMembership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->first();

        if ($existingMembership) {
            $wasLeft = $existingMembership->left_at !== null;
            $existingMembership->update([
                'group_role_override_id' => null,
                'left_at' => null,
            ]);

            if ($wasLeft && $addedBy && $member->id !== $addedBy->id) {
                $member->notify(new AddedToGroupNotification(
                    $group,
                    $addedBy->display_name ?? $addedBy->username,
                ));
            }

            $group->touch();
            WorkspaceSync::bump($group->tenant_id, ['groups', 'members']);

            return $existingMembership->fresh();
        }

        $group->members()->attach($member, [
            'group_role_override_id' => null,
            'left_at' => null,
        ]);

        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();

        if ($addedBy && $member->id !== $addedBy->id) {
            $member->notify(new AddedToGroupNotification(
                $group,
                $addedBy->display_name ?? $addedBy->username,
            ));
        }

        $group->touch();
        WorkspaceSync::bump($group->tenant_id, ['groups', 'members']);

        return $groupMember;
    }

    public function remove(Group $group, User $member): void
    {
        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();

        $groupMember->update(['left_at' => now()]);
        $group->touch();
        WorkspaceSync::bump($group->tenant_id, ['groups', 'members']);
    }

    public function assignRole(Group $group, User $member, ?GroupRoleOverride $roleOverride = null): GroupMember
    {
        $groupMember = GroupMember::where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->firstOrFail();

        $groupMember->update([
            'group_role_override_id' => $roleOverride?->id,
        ]);

        $member->notify(new GroupPermissionsChangedNotification(
            $group,
            $roleOverride ? 'New permissions in '.$group->name : 'Permissions reset in '.$group->name,
        ));

        $group->touch();
        WorkspaceSync::bump($group->tenant_id, ['groups', 'members']);

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

        $member->notify(new RoleChangedNotification($tenantRole->name, $group->name));

        WorkspaceSync::bump($group->tenant_id, ['users', 'members']);

        return $member->fresh(['tenantRole']);
    }

    public function getActive(Group $group): Collection
    {
        return GroupMember::where('group_id', $group->id)
            ->whereNull('left_at')
            ->get();
    }
}
