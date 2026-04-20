<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupRoleOverride extends Model
{
    protected $fillable = [
        'group_id',
        'tenant_role_id',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function tenantRole(): BelongsTo
    {
        return $this->belongsTo(TenantRole::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class, 'group_role_override_id');
    }
}
