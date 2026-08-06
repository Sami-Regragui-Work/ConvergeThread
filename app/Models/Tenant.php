<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Support\DisplayName;

class Tenant extends Model
{
    protected $fillable = [
        'slug',
        'admin_email',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function tenantRoles(): HasMany
    {
        return $this->hasMany(TenantRole::class);
    }

    public function closure(): HasOne
    {
        return $this->hasOne(TenantClosure::class);
    }

    public function isClosed(): bool
    {
        if ($this->relationLoaded('closure')) {
            return $this->closure !== null;
        }

        return $this->closure()->exists();
    }

    public function close(User $by): TenantClosure
    {
        return $this->closure()->create([
            'closed_by_id' => $by->id,
            'closed_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->closure()?->delete();
    }

    public function getNameAttribute(): string
    {
        return DisplayName::capitalizeFirst(str_replace('_', ' ', $this->slug)) ?? $this->slug;
    }
}
