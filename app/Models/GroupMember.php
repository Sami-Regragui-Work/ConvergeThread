<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GroupMember extends Pivot
{
    protected $table = 'group_members';

    protected $fillable = [
        'group_id',
        'user_id',
        'group_role_override_id',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'left_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groupRoleOverride(): BelongsTo
    {
        return $this->belongsTo(GroupRoleOverride::class);
    }
}
