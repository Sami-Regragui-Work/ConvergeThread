<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantRole extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function groupRoleOverrides(): HasMany
    {
        return $this->hasMany(GroupRoleOverride::class, 'tenant_role_id');
    }
}
