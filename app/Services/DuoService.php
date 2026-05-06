<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DuoService
{
    public function create(Group $group, User $user1, User $user2, string $name): Duo
    {
        if ($user1->id > $user2->id) {
            [$user1, $user2] = [$user2, $user1];
        }

        return Duo::firstOrCreate(
            [
                'group_id' => $group->id,
                'user1_id' => $user1->id,
                'user2_id' => $user2->id,
            ],
            [
                'name' => $name,
            ]
        );
    }

    public function getGroupDuos(Group $group): Collection
    {
        return $group->duos()->with(['userA', 'userB'])->get();
    }

    public function getUserDuos(Group $group, User $user): Collection
    {
        return $group->duos()
            ->with(['userA', 'userB'])
            ->where(
                fn($q) => $q
                    ->where('user1_id', $user->id)
                    ->orWhere('user2_id', $user->id)
            )
            ->get();
    }

    public function delete(Duo $duo): bool
    {
        return $duo->delete();
    }
}
