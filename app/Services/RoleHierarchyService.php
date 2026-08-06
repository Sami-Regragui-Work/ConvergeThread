<?php

namespace App\Services;

use App\Models\RoleHierarchy;
use App\Models\RoleHierarchyLevel;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class RoleHierarchyService
{
    /** Default rank when a role is not mapped in a custom hierarchy. */
    private const SYSTEM_ROLE_RANKS = [
        'Admin' => 1000,
        'Moderator' => 500,
        'Member' => 100,
    ];

    private const DEFAULT_CUSTOM_ROLE_RANK = 150;

    public function isTenantFounder(User $user): bool
    {
        $tenant = $user->tenant;

        return $tenant
            && strcasecmp($user->email, $tenant->admin_email) === 0;
    }

    public function systemRankForRole(?TenantRole $role): int
    {
        if (!$role) {
            return 0;
        }

        if (isset(self::SYSTEM_ROLE_RANKS[$role->name])) {
            return self::SYSTEM_ROLE_RANKS[$role->name];
        }

        return self::DEFAULT_CUSTOM_ROLE_RANK;
    }

    public function effectiveRank(User $user): int
    {
        $systemRank = $this->systemRankForRole($user->tenantRole);

        $bestHierarchyRank = RoleHierarchyLevel::query()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->min('level');

        if ($bestHierarchyRank === null) {
            return $systemRank;
        }

        // Level 0 is top; convert to rank (level 0 → 2000, level 1 → 1990, …)
        $hierarchyRank = 2000 - ((int) $bestHierarchyRank * 10);

        return max($systemRank, $hierarchyRank);
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if ((int) $actor->tenant_id !== (int) $target->tenant_id) {
            return false;
        }

        if ($actor->id === $target->id) {
            return false;
        }

        if ($this->isTenantFounder($actor)) {
            return true;
        }

        if ($this->isTenantFounder($target) && !$this->isTenantFounder($actor)) {
            return false;
        }

        if ($this->hierarchyBlocksManagement($actor, $target)) {
            return false;
        }

        return $this->effectiveRank($actor) > $this->effectiveRank($target);
    }

    public function canAssignRole(User $actor, User $target, TenantRole $role): bool
    {
        if (!$role->isUsableByTenant($actor->tenant_id)) {
            return false;
        }

        if (!$this->canManageUser($actor, $target)) {
            return false;
        }

        if ($this->isTenantFounder($actor)) {
            return true;
        }

        $actorRank = $this->effectiveRank($actor);
        $roleRank = $this->systemRankForRole($role);
        $targetRank = $this->effectiveRank($target);

        if ($roleRank >= $actorRank) {
            return false;
        }

        if ($role->name === 'Admin' && !$this->isTenantFounder($actor)) {
            return false;
        }

        return $roleRank > $targetRank || $target->tenant_role_id === $role->id;
    }

    public function assignableRolesFor(User $actor, ?User $target = null): Collection
    {
        $roles = TenantRole::query()
            ->forTenant($actor->tenant_id)
            ->orderBy('name')
            ->get();

        if ($target === null) {
            return $roles->filter(function (TenantRole $role) use ($actor) {
                if ($role->name === 'Admin' && !$this->isTenantFounder($actor)) {
                    return false;
                }

                return $this->systemRankForRole($role) < $this->effectiveRank($actor);
            })->values();
        }

        return $roles->filter(
            fn (TenantRole $role) => $this->canAssignRole($actor, $target, $role),
        )->values();
    }

    public function assertCanAssignRole(User $actor, User $target, TenantRole $role): void
    {
        if (!$this->canAssignRole($actor, $target, $role)) {
            throw new InvalidArgumentException('You cannot assign this role to this member.');
        }
    }

    public function hierarchyBlocksManagement(User $actor, User $target): bool
    {
        $shared = RoleHierarchy::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereHas('levels.members', fn ($q) => $q->where('users.id', $actor->id))
            ->whereHas('levels.members', fn ($q) => $q->where('users.id', $target->id))
            ->with(['levels' => fn ($q) => $q->with('members:id')])
            ->get();

        foreach ($shared as $hierarchy) {
            $actorLevel = $this->levelInHierarchy($hierarchy, $actor);
            $targetLevel = $this->levelInHierarchy($hierarchy, $target);

            if ($actorLevel !== null && $targetLevel !== null && $actorLevel >= $targetLevel) {
                return true;
            }
        }

        return false;
    }

    private function levelInHierarchy(RoleHierarchy $hierarchy, User $user): ?int
    {
        foreach ($hierarchy->levels as $level) {
            if ($level->members->contains('id', $user->id)) {
                return $level->level;
            }
        }

        return null;
    }
}
