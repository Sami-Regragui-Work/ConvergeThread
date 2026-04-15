<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Pivot
{
    protected $table = 'group_members';

    protected $fillable = [
        'group_role_override_id',
        'permissions',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
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