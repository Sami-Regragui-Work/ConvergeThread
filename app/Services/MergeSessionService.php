<?php

namespace App\Services;

use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;
use App\Notifications\MergeSessionStartedNotification;
use App\Services\ChatParticipantService;
use App\Support\WorkspaceSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MergeSessionService
{
    public function __construct(
        private readonly ChatParticipantService $participantService,
    ) {
    }

    public function start(Group $group1, Group $group2): MergeSession
    {
        return DB::transaction(function () use ($group1, $group2) {
            $session = MergeSession::create([
                'started_at' => now(),
            ]);

            $session->mergeSessionGroups()->createMany([
                ['group_id' => $group1->id],
                ['group_id' => $group2->id],
            ]);

            $notified = [];
            foreach ([$group1, $group2] as $group) {
                foreach ($this->participantService->participants($group) as $user) {
                    if (isset($notified[$user->id])) {
                        continue;
                    }
                    $notified[$user->id] = true;
                    $user->notify(new MergeSessionStartedNotification($session, $group->name));
                }
            }

            WorkspaceSync::bump($group1->tenant_id, ['merges']);

            return $session;
        });
    }

    public function end(MergeSession $session): MergeSession
    {
        $session->update(['ended_at' => now()]);
        $session->loadMissing('groups');
        $tenantId = $session->groups->first()?->tenant_id;
        WorkspaceSync::bump($tenantId, ['merges']);

        return $session->fresh();
    }

    public function getActiveForTenant(int $tenantId): Collection
    {
        return MergeSession::query()
            ->forTenant($tenantId)
            ->whereNull('ended_at')
            ->with('groups')
            ->get();
    }
}
