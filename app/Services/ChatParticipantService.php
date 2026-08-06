<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\MergeSession;
use App\Models\User;
use Illuminate\Support\Collection;

class ChatParticipantService
{
    public function __construct(
        private readonly GroupPermissionService $groupPermissionService,
    ) {
    }

    public function participants(Group|Duo|MergeSession $chatable): Collection
    {
        return match (true) {
            $chatable instanceof Group => $this->groupParticipants($chatable),
            $chatable instanceof Duo => collect([$chatable->user1, $chatable->user2])->filter(),
            $chatable instanceof MergeSession => $this->mergeParticipants($chatable),
            default => collect(),
        };
    }

    private function groupParticipants(Group $group): Collection
    {
        $userIds = GroupMember::query()
            ->where('group_id', $group->id)
            ->whereNull('left_at')
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->with('tenantRole')
            ->get();
    }

    private function mergeParticipants(MergeSession $mergeSession): Collection
    {
        $mergeSession->loadMissing('groups');

        return $mergeSession->groups
            ->flatMap(fn (Group $group) => $this->groupParticipants($group))
            ->unique('id')
            ->values();
    }

    public function groupsInChat(Group|Duo|MergeSession $chatable): Collection
    {
        return match (true) {
            $chatable instanceof Group => collect([$chatable]),
            $chatable instanceof Duo => collect([$chatable->group])->filter(),
            $chatable instanceof MergeSession => $chatable->groups,
            default => collect(),
        };
    }

    public function groupForUserInChat(User $user, Group|Duo|MergeSession $chatable): ?Group
    {
        if ($chatable instanceof Group) {
            return $chatable;
        }

        if ($chatable instanceof Duo) {
            return $chatable->group;
        }

        if ($chatable instanceof MergeSession) {
            $chatable->loadMissing('groups');

            return $chatable->groups->first(function (Group $group) use ($user) {
                return GroupMember::query()
                    ->where('group_id', $group->id)
                    ->where('user_id', $user->id)
                    ->whereNull('left_at')
                    ->exists();
            });
        }

        return null;
    }

    public function displayLabelFor(User $user, Group|Duo|MergeSession $chatable, string $chatType = 'group'): string
    {
        $display = $user->display_name ?? $user->username ?? $user->email;

        if ($chatType !== 'merge') {
            return $display;
        }

        $group = $this->groupForUserInChat($user, $chatable);

        return $group ? $group->name.'/'.$display : $display;
    }
}
