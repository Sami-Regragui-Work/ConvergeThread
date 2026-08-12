<?php

namespace App\Models;

use App\Support\DisplayName;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Group extends Model
{
    public const ACCENT_PALETTE = [
        '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e',
        '#14b8a6', '#0ea5e9', '#6366f1', '#a855f7', '#ec4899',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'accent_color',
        'creator_id',
    ];

    protected function casts(): array
    {
        return [
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->using(GroupMember::class)
            ->withPivot(['group_role_override_id', 'left_at'])
            ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivotNull('left_at');
    }

    public function groupRoleOverrides(): HasMany
    {
        return $this->hasMany(GroupRoleOverride::class);
    }

    public function duos(): HasMany
    {
        return $this->hasMany(Duo::class);
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'chatable');
    }

    public function monogramInitials(): string
    {
        return strtoupper(mb_substr($this->name ?? 'G', 0, 2));
    }

    public function accentColor(): string
    {
        if ($this->accent_color) {
            return $this->accent_color;
        }

        return self::ACCENT_PALETTE[crc32((string) $this->id) % count(self::ACCENT_PALETTE)];
    }
}
