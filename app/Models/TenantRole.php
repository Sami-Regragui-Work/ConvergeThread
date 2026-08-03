<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantRole extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'permissions',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
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

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $inner) use ($tenantId): void {
            $inner->where('tenant_id', $tenantId)
                ->orWhere('is_system', true);
        });
    }

    public function isUsableByTenant(?int $tenantId): bool
    {
        return $this->is_system || (int) $this->tenant_id === (int) $tenantId;
    }

    public static function assignableForInviter(User $inviter): Collection
    {
        $query = static::query()->forTenant($inviter->tenant_id)->orderBy('name');

        if ($inviter->tenantRole?->name !== 'Admin') {
            $query->where('name', '!=', 'Admin');
        }

        return $query->get();
    }
}
