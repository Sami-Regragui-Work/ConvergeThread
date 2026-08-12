<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RoleHierarchyLevel extends Model
{
    protected $fillable = [
        'role_hierarchy_id',
        'parent_id',
        'level',
        'label',
        'kind',
        'group_id',
        'role_id',
    ];

    protected function label(): Attribute
    {
        // The label always tracks depth: level 0 is the top level, anything
        // deeper is "Level N". It is derived so it can never drift out of
        // sync when a node is linked/unlinked.
        return Attribute::make(
            get: fn (?string $value) => (int) $this->level === 0 ? 'Top level' : 'Level '.((int) $this->level),
        );
    }

    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(RoleHierarchy::class, 'role_hierarchy_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(TenantRole::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_hierarchy_level_user')
            ->withTimestamps();
    }

    /**
     * All user ids that occupy this node. Group nodes resolve to the
     * group's active members; member nodes resolve to the attached users.
     */
    public function occupantUserIds(): Collection
    {
        if ($this->kind === 'group' && $this->group) {
            return $this->group->activeMembers()->pluck('users.id');
        }

        return $this->members()->pluck('users.id');
    }

    /**
     * Node ids for the whole subtree (this node + all descendants).
     */
    public function subtreeIds(Collection $all): Collection
    {
        $ids = collect([$this->id]);

        foreach ($all->where('parent_id', $this->id) as $child) {
            $ids = $ids->merge($child->subtreeIds($all));
        }

        return $ids->unique()->values();
    }

    /**
     * Node ids on the path from this node up to the root.
     */
    public function ancestorIds(): Collection
    {
        $ids = collect();
        $current = $this;

        while ($current->parent_id !== null) {
            $ids->push((int) $current->parent_id);
            $current = $current->parent;
        }

        return $ids->unique()->values();
    }
}
