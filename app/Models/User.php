<?php

namespace App\Models;

use App\Support\DisplayName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'username',
        'display_name',
        'tenant_id',
        'tenant_role_id',
        'banned_by_id',
        'e2ee_public_key',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'e2ee_public_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenantRole(): BelongsTo
    {
        return $this->belongsTo(TenantRole::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->using(GroupMember::class)
            ->withPivot(['group_role_override_id', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at');
    }

    public function createdGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'creator_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function invitationsSent(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_id');
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by_id');
    }

    public function bannedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'banned_by_id');
    }

    public function isOwner(): bool
    {
        return $this->tenant_id === 1;
    }

    public function displayLabel(): string
    {
        if ($this->display_name !== null && $this->display_name !== '') {
            return $this->display_name;
        }

        if ($this->username !== null && $this->username !== '') {
            return $this->username;
        }

        return $this->email;
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => DisplayName::capitalizeFirst($value),
            set: fn (?string $value) => $value,
        );
    }
}
