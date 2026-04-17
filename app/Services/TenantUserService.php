<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

class TenantUserService
{
    public function generateUniqueTenantUsername(string $baseName, Tenant $tenant): string
    {
        $username = Str::slug($baseName);

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
}
