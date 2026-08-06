<?php

namespace App\Models;

use App\Support\DisplayName;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoleHierarchy extends Model
{
    protected $fillable = ['tenant_id', 'name'];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => DisplayName::capitalizeFirst($value),
        );
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(RoleHierarchyLevel::class)->orderBy('level');
    }
}
