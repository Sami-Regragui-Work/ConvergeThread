<?php

namespace App\Models;

use App\Support\DisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $admin_email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method mixed getKey()
 */
class Tenant extends Model
{
    protected $fillable = [
        'slug',
        'name',
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
        return $this->attributes['name']
            ?? DisplayName::capitalizeFirst(str_replace('_', ' ', $this->slug)) ?? $this->slug;
    }
}
