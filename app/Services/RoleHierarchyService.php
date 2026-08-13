<?php

namespace App\Services;

use App\Models\Group;
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
        if (! $role) {
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

        if ($this->isTenantFounder($target) && ! $this->isTenantFounder($actor)) {
            return false;
        }

        if ($this->hierarchyBlocksManagement($actor, $target)) {
            return false;
        }

        return $this->effectiveRank($actor) > $this->effectiveRank($target);
    }

    public function canAssignRole(User $actor, User $target, TenantRole $role): bool
    {
        if (! $role->isUsableByTenant($actor->tenant_id)) {
            return false;
        }

        if (! $this->canManageUser($actor, $target)) {
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

        if ($role->name === 'Admin' && ! $this->isTenantFounder($actor)) {
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
                if ($role->name === 'Admin' && ! $this->isTenantFounder($actor)) {
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
        if (! $this->canAssignRole($actor, $target, $role)) {
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
            $actorNode = $this->nodeInHierarchy($hierarchy, $actor);
            $targetNode = $this->nodeInHierarchy($hierarchy, $target);

            if ($actorNode === null || $targetNode === null) {
                continue;
            }

            // Only a vertical (ancestor / descendant) relation blocks:
            // members in unrelated branches may both be managed by a third party.
            if ($this->isAncestorOrSelf($actorNode, $targetNode)) {
                return true;
            }
        }

        return false;
    }

    private function nodeInHierarchy(RoleHierarchy $hierarchy, User $user): ?RoleHierarchyLevel
    {
        foreach ($hierarchy->levels as $level) {
            if ($level->members->contains('id', $user->id)) {
                return $level;
            }
        }

        return null;
    }

    private function isAncestorOrSelf(RoleHierarchyLevel $ancestor, RoleHierarchyLevel $node): bool
    {
        if ((int) $ancestor->id === (int) $node->id) {
            return true;
        }

        $current = $node;

        while ($current->parent_id !== null) {
            $parent = $current->parent;

            if (! $parent) {
                break;
            }

            if ((int) $parent->id === (int) $ancestor->id) {
                return true;
            }

            $current = $parent;
        }

        return false;
    }

    /**
     * Create a node (level) in a hierarchy. $parent === null creates an
     * unlinked top-level node (level 0) that can hold anyone until it is
     * linked under another node (linking runs the contradiction checks).
     */
    public function createNode(
        RoleHierarchy $hierarchy,
        ?RoleHierarchyLevel $parent = null,
        string $kind = 'member',
        ?int $groupId = null,
        ?int $roleId = null
    ): RoleHierarchyLevel {
        return RoleHierarchyLevel::create([
            'role_hierarchy_id' => $hierarchy->id,
            'parent_id' => $parent?->id,
            'level' => $parent ? (int) $parent->level + 1 : 0,
            'kind' => $kind,
            'group_id' => $groupId,
            'role_id' => $roleId,
        ]);
    }

    /**
     * Recompute the depth (level) of a node and every descendant.
     */
    public function recomputeLevels(RoleHierarchyLevel $node): void
    {
        $all = RoleHierarchyLevel::where('role_hierarchy_id', $node->role_hierarchy_id)
            ->get(['id', 'parent_id']);

        $this->applyLevel($node, $all);
    }

    private function applyLevel(RoleHierarchyLevel $node, Collection $all): void
    {
        $node->update(['level' => $node->parent_id ? (int) $node->parent()->value('level') + 1 : 0]);

        foreach ($all->where('parent_id', $node->id) as $child) {
            $this->applyLevel($child, $all);
        }
    }

    /**
     * All user ids on the path a new member of $node would occupy
     * (ancestors + node + descendants). A user must not appear twice
     * on the same root→leaf path.
     */
    private function pathOccupantUserIds(RoleHierarchyLevel $node): Collection
    {
        $all = RoleHierarchyLevel::where('role_hierarchy_id', $node->role_hierarchy_id)
            ->with(['group', 'members:id'])
            ->get();

        $ids = collect();

        foreach ($node->ancestorIds() as $id) {
            $ids = $ids->merge($all->firstWhere('id', $id)?->occupantUserIds() ?? collect());
        }

        $ids = $ids->merge($node->occupantUserIds());

        foreach ($node->subtreeIds($all)->filter(fn ($id) => $id !== (int) $node->id) as $id) {
            $ids = $ids->merge($all->firstWhere('id', $id)?->occupantUserIds() ?? collect());
        }

        return $ids->unique();
    }

    /**
     * Return the subset of $userIds that already sit on $node's vertical path
     * and therefore cannot be placed here.
     *
     * @param  array<int>  $userIds
     */
    public function rejectedUserIds(RoleHierarchyLevel $node, array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        $onPath = $this->pathOccupantUserIds($node);

        return collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $onPath->contains($id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Return the subset of $roleIds that already sit on $node's vertical path.
     *
     * @param  array<int>  $roleIds
     */
    public function rejectedRoleIds(RoleHierarchyLevel $node, array $roleIds): array
    {
        if (! $roleIds) {
            return [];
        }

        $all = RoleHierarchyLevel::where('role_hierarchy_id', $node->role_hierarchy_id)
            ->get(['id', 'parent_id', 'role_id']);

        $onPath = collect([$node->role_id])
            ->merge($node->ancestorIds()->map(fn ($id) => $all->firstWhere('id', $id)?->role_id))
            ->filter();

        foreach ($node->subtreeIds($all)->filter(fn ($id) => $id !== (int) $node->id) as $id) {
            $onPath->push($all->firstWhere('id', $id)?->role_id);
        }

        return collect($roleIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $onPath->unique()->contains($id))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Link $node under $parent (null unlinks it back into a top-level node).
     *
     * @return array<string> human-readable contradictions, empty on success
     */
    public function linkNode(RoleHierarchyLevel $node, ?RoleHierarchyLevel $parent): array
    {
        if ($parent && (int) $parent->id === (int) $node->id) {
            return ['A node cannot be its own parent.'];
        }

        if ($parent) {
            $all = RoleHierarchyLevel::where('role_hierarchy_id', $node->role_hierarchy_id)
                ->get(['id', 'parent_id']);

            if ($node->subtreeIds($all)->contains((int) $parent->id)) {
                return ['That would create a circular link (the target is inside this node already).'];
            }
        }

        if ($node->kind === 'role') {
            $conflicts = $this->linkedRoleConflicts($node, $parent);
        } else {
            $conflicts = $this->linkedUserConflicts($node, $parent);
        }

        if ($conflicts->isNotEmpty()) {
            return ['Contradiction: '.$conflicts->join(', ').' already sit in an ancestor/descendant position.'];
        }

        $node->parent_id = $parent?->id;
        $node->save();

        $this->recomputeLevels($node);

        return [];
    }

    /**
     * Insert a brand-new empty level above $node. The new level inherits the
     * node's current position (same parent) and $node moves one step down.
     * Since the new level holds nobody, no contradiction checks are needed.
     */
    public function addParentLevel(RoleHierarchyLevel $node): RoleHierarchyLevel
    {
        $kind = $node->hierarchy->kind === 'role' ? 'role' : 'member';

        $parent = RoleHierarchyLevel::create([
            'role_hierarchy_id' => $node->role_hierarchy_id,
            'parent_id' => $node->parent_id,
            'level' => $node->parent_id ? (int) $node->parent()->value('level') + 1 : 0,
            'kind' => $kind,
        ]);

        $node->parent_id = $parent->id;
        $node->save();

        $this->recomputeLevels($node);

        return $parent;
    }

    private function linkedUserConflicts(RoleHierarchyLevel $node, ?RoleHierarchyLevel $parent): Collection
    {
        $all = RoleHierarchyLevel::where('role_hierarchy_id', $node->role_hierarchy_id)
            ->with(['group', 'members:id'])
            ->get();

        $nodeUsers = collect();
        foreach ($node->subtreeIds($all) as $id) {
            $nodeUsers = $nodeUsers->merge($all->firstWhere('id', $id)?->occupantUserIds() ?? collect());
        }

        $parentChain = collect();
        $cursor = $parent;
        while ($cursor) {
            $parentChain = $parentChain->merge($cursor->occupantUserIds());
            $cursor = $all->firstWhere('id', $cursor->parent_id);
        }

        $overlap = $nodeUsers->unique()->filter(fn ($uid) => $parentChain->contains($uid));

        return User::whereIn('id', $overlap)->pluck('display_name');
    }

    private function linkedRoleConflicts(RoleHierarchyLevel $node, ?RoleHierarchyLevel $parent): Collection
    {
        $all = RoleHierarchyLevel::where('role_hierarchy_id', $node->role_hierarchy_id)
            ->get(['id', 'parent_id', 'role_id']);

        $nodeRoles = collect();
        foreach ($node->subtreeIds($all) as $id) {
            $nodeRoles->push($all->firstWhere('id', $id)?->role_id);
        }

        $parentChain = collect();
        $cursor = $parent;
        while ($cursor) {
            $parentChain->push($cursor->role_id);
            $cursor = $all->firstWhere('id', $cursor->parent_id);
        }

        $overlap = $nodeRoles->filter(fn ($rid) => $rid && $parentChain->contains($rid));

        return TenantRole::whereIn('id', $overlap)->pluck('name');
    }

    /**
     * Attach $userIds to a member node. Returns the rejected ids.
     *
     * @param  array<int>  $userIds
     * @return array<int>
     */
    public function syncNodeMembers(RoleHierarchyLevel $node, array $userIds): array
    {
        $rejected = $this->rejectedUserIds($node, $userIds);

        $node->members()->sync(collect($userIds)->map(fn ($id) => (int) $id)->reject(fn ($id) => in_array($id, $rejected)));

        return $rejected;
    }

    /**
     * Point a group node at a group; returns rejected member ids.
     *
     * @return array<int>
     */
    public function attachGroupToNode(RoleHierarchyLevel $node, Group $group): array
    {
        $rejected = $this->rejectedUserIds($node, $group->activeMembers()->pluck('users.id')->all());

        $node->update(['kind' => 'group', 'group_id' => $group->id, 'role_id' => null]);
        $node->members()->detach();

        return $rejected;
    }

    /**
     * Point a role node at a tenant role; returns rejected role ids.
     */
    public function attachRoleToNode(RoleHierarchyLevel $node, TenantRole $role): ?int
    {
        $rejected = $this->rejectedRoleIds($node, [$role->id]);

        if ($rejected) {
            return $rejected[0];
        }

        $node->update(['kind' => 'role', 'role_id' => $role->id, 'group_id' => null]);
        $node->members()->detach();

        return null;
    }

    /**
     * Serializable payload for the interactive hierarchy map. Shared by the
     * server-rendered partial and the JSON refresh endpoint so the client can
     * mutate nodes without reloading the page.
     *
     * @return array{id:int,name:string,kind:string,nodes:array<int,array<string,mixed>>}
     */
    public function mapPayload(RoleHierarchy $hierarchy): array
    {
        $hierarchy->loadMissing(['levels' => fn ($q) => $q
            ->with(['members:id,display_name,username,email,avatar_color', 'group:id,name', 'role:id,name'])
            ->orderBy('level')->orderBy('id')]);

        $levels = $hierarchy->levels;

        // Effective tag per node: the stored tag, or "(n)" numbering same-level
        // nodes by id so otherwise-identical "Level N" cards can be told apart.
        $effectiveTags = [];
        $levels->groupBy('level')->each(function ($group) use (&$effectiveTags) {
            foreach ($group->sortBy('id')->values() as $i => $level) {
                $stored = $level->tag === null ? null : trim($level->tag);
                $effectiveTags[(int) $level->id] = ($stored !== null && $stored !== '')
                    ? $stored
                    : '('.($i + 1).')';
            }
        });
        $displayName = fn (RoleHierarchyLevel $level) => $level->label.' '.$effectiveTags[(int) $level->id];

        // Candidate "add parent" targets per node: every node that is not the node
        // itself and not inside its subtree (linking would create a cycle).
        $linkTargets = $levels->mapWithKeys(function ($level) use ($levels, $displayName) {
            $subtree = $level->subtreeIds($levels)->map(fn ($id) => (int) $id)->all();

            return [(int) $level->id => $levels
                ->reject(fn ($n) => in_array((int) $n->id, $subtree, true))
                ->map(fn ($n) => ['id' => (int) $n->id, 'name' => $displayName($n)])
                ->values()
                ->all()];
        });

        $nodes = $levels->map(function ($level) use ($linkTargets, $effectiveTags, $displayName) {
            return [
                'id' => (int) $level->id,
                'level' => (int) $level->level,
                'kind' => $level->kind,
                'parent_id' => $level->parent_id === null ? null : (int) $level->parent_id,
                'label' => $level->label,
                'tag' => $level->tag,
                'tag_effective' => $effectiveTags[(int) $level->id],
                'display' => $displayName($level),
                'group_name' => $level->group?->name,
                'group_member_count' => (int) ($level->group?->active_members_count ?? 0),
                'role_name' => $level->role?->name,
                'role_id' => $level->role_id === null ? null : (int) $level->role_id,
                'members' => $level->members->map(fn ($m) => [
                    'id' => (int) $m->id,
                    'name' => $m->displayLabel(),
                    'initial' => $m->avatarInitial(),
                    'color' => $m->avatarColor(),
                ])->values()->all(),
                'link_targets' => $linkTargets[(int) $level->id],
                'urls' => [
                    'link' => route('hierarchies.levels.link', $level),
                    'addParent' => route('hierarchies.levels.add-parent', $level),
                    'members' => route('hierarchies.levels.members', $level),
                    'group' => route('hierarchies.levels.group', $level),
                    'role' => route('hierarchies.levels.role', $level),
                    'member' => route('hierarchies.levels.member', $level),
                    'tag' => route('hierarchies.levels.tag', $level),
                    'destroy' => route('hierarchies.levels.destroy', $level),
                ],
            ];
        })->values()->all();

        return [
            'id' => (int) $hierarchy->id,
            'name' => $hierarchy->name,
            'kind' => $hierarchy->kind,
            'nodes' => $nodes,
        ];
    }
}
