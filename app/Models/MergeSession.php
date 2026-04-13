<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function groups(): HasManyThrough
    {
        return $this->hasManyThrough(Group::class, MergeSessionGroup::class);
    }


    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
