<?php

namespace App\Support;

use App\Events\OwnerDashboardUpdated;
use App\Events\WorkspaceUpdated;

class WorkspaceSync
{
    /**
     * @param  list<string>|string  $scopes
     */
    public static function bump(?int $tenantId, array|string $scopes = ['workspace']): void
    {
        $scopes = is_array($scopes) ? $scopes : [$scopes];
        $scopes = array_values(array_unique($scopes));

        if ($tenantId) {
            WorkspaceUpdated::dispatch($tenantId, $scopes);
        }

        OwnerDashboardUpdated::dispatch($scopes);
    }
}
