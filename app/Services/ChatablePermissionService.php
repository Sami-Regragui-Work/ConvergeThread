<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;

class ChatablePermissionService
{
    public function __construct(
        private readonly GroupPermissionService $groupPermissionService
    ) {
    }

    public function hasPermission(Group|Duo|MergeSession $chatable, User $user, string $permission): bool
    {
        if ($user->banned_by_id !== null) {
            return false;
        }

        return match (true) {
            $chatable instanceof Group => $this->hasGroupPermission($chatable, $user, $permission),
            $chatable instanceof Duo => $this->hasDuoPermission($chatable, $user, $permission),
            $chatable instanceof MergeSession => $this->hasMergeSessionPermission($chatable, $user, $permission),
            default => false,
        };
    }

    private function hasGroupPermission(Group $group, User $user, string $permission): bool
    {
        return $this->groupPermissionService->hasPermission($group, $user, $permission);
    }

    private function hasDuoPermission(Duo $duo, User $user, string $permission): bool
    {
        if (!$duo->group) {
            return false;
        }

        if ((string) $duo->group->tenant_id != (string) $user->tenant_id) {
            return false;
        }

        $isParticipant = (string) $duo->user1_id == (string) $user->id
            || (string) $duo->user2_id == (string) $user->id;

        if (!$isParticipant) {
            return false;
        }

        return $this->groupPermissionService->hasPermission($duo->group, $user, $permission);
    }

    private function hasMergeSessionPermission(MergeSession $mergeSession, User $user, string $permission): bool
    {
        $groups = $mergeSession->relationLoaded('groups')
            ? $mergeSession->groups
            : $mergeSession->groups()->get();

        foreach ($groups as $group) {
            if ((string) $group->tenant_id != (string) $user->tenant_id) {
                continue;
            }

            $isActiveMember = $group->activeMembers()
                ->whereKey($user->id)
                ->exists();

            if (!$isActiveMember) {
                continue;
            }

            if ($this->groupPermissionService->hasPermission($group, $user, $permission)) {
                return true;
            }
        }

        return false;
    }
}
