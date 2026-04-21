<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\User;

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

    public function getUserDuos(Group $group, User $user): array
    {
        return [
            'owned' => $group->duos()
                ->where(
                    fn($query) => $query
                        ->where('user1_id', $user->id)
                        ->orWhere('user2_id', $user->id)
                )
                ->get(),
            'visible' => $group->duos()->get(),
        ];
    }

    public function delete(Duo $duo): bool
    {
        return $duo->delete();
    }
}
