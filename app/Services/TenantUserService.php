<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantUserService
{
    public function generateUniqueTenantUsername(string $displayName, Tenant $tenant): string
    {
        $username = Str::slug($displayName, '_');

        if (!User::where('tenant_id', $tenant->id)->where('username', $username)->exists())
            return $username;

        $usernames = User::where('tenant_id', $tenant->id)
            ->whereLike('username', "$username%")
            ->pluck('username')
            ->toArray();

        $numbers = array_map(function ($str) {
            $split = explode('-', $str, 2);
            if (count($split) == 2 && is_numeric($split[1])) {
                return (int) $split[1];
            }
            return -1;
        }, $usernames);

        $currentNumber = empty($numbers) ? -1 : max($numbers) + 1;

        if ($currentNumber == -1)
            return $username;
        return "$username-$currentNumber";
    }

    public function findTenantBySlug(string $slug): Tenant
    {
        $tenant = Tenant::where('name', $slug)->first();
        if (!$tenant) {
            throw new InvalidArgumentException("Tenant '{$slug}' not found", 422);
        }
        return $tenant;
    }
}
