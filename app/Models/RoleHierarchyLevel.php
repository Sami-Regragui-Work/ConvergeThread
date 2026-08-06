<?php

namespace App\Models;

use App\Support\DisplayName;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RoleHierarchyLevel extends Model
{
    protected $fillable = ['role_hierarchy_id', 'level', 'label'];

    protected function label(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => DisplayName::capitalizeFirst($value),
        );
    }
    public function hierarchy(): BelongsTo
    {
        return $this->belongsTo(RoleHierarchy::class, 'role_hierarchy_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_hierarchy_level_user')
            ->withTimestamps();
    }
}
