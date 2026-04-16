<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationService
{
    public function createOwnerInvitation(string $email, User $owner): Invitation
    {
        return Invitation::create([
            'tenant_id' => 0,
            'invited_by_id' => $owner->id,
            'email' => $email,
            'token' => Str::random(60),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function createTenantInvitation(
        string $email,
        User $invitedBy,
        Tenant $tenant,
        ?Group $group = null,
        ?TenantRole $tenantRole = null
    ): Invitation {
        return Invitation::create([
            'tenant_id' => $tenant->id,
            'group_id' => $group->id,
            'invited_by_id' => $invitedBy->id,
            'tenant_role_id' => $tenantRole->id,
            'email' => $email,
            'token' => Str::random(60),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function acceptInvitation(string $token, string $password, ?string $displayName = null): array
    {
        $invitation = Invitation::where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return DB::transaction(function () use ($invitation, $password, $displayName) {
            $username = $this->generateUniqueTenantUsername(
                $displayName ?? $invitation->email,
                $invitation->tenant_id
            );

            $user = User::create([
                'email' => $invitation->email,
                'password' => Hash::make($password),
                'username' => $username,
                'display_name' => $displayName,
                'tenant_id' => $invitation->tenant_id,
                'tenant_role_id' => $invitation->tenant_role_id,
            ]);

            $invitation->update(['accepted_at' => now()]);

            return [
                'user' => $user->load('tenant', 'tenantRole'),
                'invitation' => $invitation->load('invitedBy'),
            ];
        });
    }

    private function generateUniqueTenantUsername(string $baseName, Tenant $tenant): string
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
