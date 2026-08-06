<?php

namespace App\Models;

use App\Support\DisplayName;
use App\Services\RoleHierarchyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantRole extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'color',
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

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => DisplayName::capitalizeFirst($value),
            set: fn (?string $value) => $value,
        );
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
        return app(RoleHierarchyService::class)->assignableRolesFor($inviter);
    }
}
