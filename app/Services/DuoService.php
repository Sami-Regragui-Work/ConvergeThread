<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\User;
use App\Support\WorkspaceSync;
use Illuminate\Database\Eloquent\Collection;

class DuoService
{
    public function create(Group $group, User $user1, User $user2, string $name): Duo
    {
        if ($user1->id > $user2->id) {
            [$user1, $user2] = [$user2, $user1];
        }

        $duo = Duo::firstOrCreate(
            [
                'group_id' => $group->id,
                'user1_id' => $user1->id,
                'user2_id' => $user2->id,
            ],
            [
                'name' => $name,
            ]
        );

        if ($duo->wasRecentlyCreated) {
            WorkspaceSync::bump($group->tenant_id, ['duos', 'groups']);
        }

        return $duo;
    }

    public function getGroupDuos(Group $group): Collection
    {
        return $group->duos()->with(['user1', 'user2'])->get();
    }

    public function getUserDuos(Group $group, User $user): Collection
    {
        return $group->duos()
            ->with(['user1', 'user2'])
            ->where(
                fn($q) => $q
                    ->where('user1_id', $user->id)
                    ->orWhere('user2_id', $user->id)
            )
            ->get();
    }

    public function delete(Duo $duo): bool
    {
        $tenantId = $duo->group?->tenant_id ?? $duo->group()->value('tenant_id');
        $deleted = (bool) $duo->delete();

        if ($deleted) {
            WorkspaceSync::bump($tenantId, ['duos', 'groups']);
        }

        return $deleted;
    }
}
