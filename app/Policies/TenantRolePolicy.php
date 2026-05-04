<?php

namespace App\Policies;

use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TenantRolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $viewer): bool
    {
        return $this->isTenantAdmin($viewer);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator): bool
    {
        return $this->isTenantAdmin($creator);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, TenantRole $tenantRole): bool
    {
        if (!$this->isTenantAdmin($deleter)) {
            return false;
        }

        return $deleter->tenant_id === $tenantRole->tenant_id;
    }

    private function isTenantAdmin(User $user): bool
    {
        if ($user->banned_by_id !== null || !$user->tenantRole) {
            return false;
        }

        return $user->tenantRole->name === 'admin';
    }
}
