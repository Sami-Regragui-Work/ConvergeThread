<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MergeSession extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
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
}
