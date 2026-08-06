<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MergeSession extends Model
{
    public $fillable = [
        'started_at',
        'ended_at',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query
            ->whereHas('groups', fn (Builder $inner) => $inner->where('tenant_id', $tenantId))
            ->whereDoesntHave('groups', fn (Builder $inner) => $inner->where('tenant_id', '!=', $tenantId));
    }

    public function mergeSessionGroups(): HasMany
    {
        return $this->hasMany(MergeSessionGroup::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'merge_session_groups');
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'chatable');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function getNameAttribute(): string
    {
        $groups = $this->relationLoaded('groups')
            ? $this->groups
            : $this->groups()->get();

        if ($groups->count() >= 2) {
            return $groups->get(0)->name . ' ↔ ' . $groups->get(1)->name;
        }

        return 'Merge Session #' . $this->id;
    }
}
