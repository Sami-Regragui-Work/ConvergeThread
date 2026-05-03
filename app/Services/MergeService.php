<?php

namespace App\Services;

use App\Models\Group;
use App\Models\MergeSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MergeService
{
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

            return $session;
        });
    }

    public function end(MergeSession $session): MergeSession
    {
        $session->update(['ended_at' => now()]);
        return $session->fresh();
    }

    public function getActive(): Collection
    {
        return MergeSession::whereNull('ended_at')
            ->get();
    }
}
