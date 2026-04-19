<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class GroupService
{
    public function create(string $name, User $creator): Group
    {
        if ($creator->tenant_id === 0) {
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

    public function updateName(Group $group, User $updater, string $name): Group
    {
        if ($updater->id !== $group->creator_id) {
            throw new AuthorizationException('Only creator can update group name.');
        }

        $group->update(['name' => $name]);
        return $group->fresh();
    }

    public function delete(Group $group, User $deleter): void
    {
        if ($deleter->id !== $group->creator_id) {
            throw new AuthorizationException('Only creator can delete.');
        }

        $group->delete();
    }
}
