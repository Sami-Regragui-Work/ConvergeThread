<?php

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
    public function __construct(private TenantUserService $tenantUserService)
    {
    }
    public function createAdminInvitation(string $email, User $owner): Invitation
    {
        return Invitation::create([
            'tenant_id' => 0,
            'invited_by_id' => $owner->id,
            'email' => $email,
            'token' => Str::random(60),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function createMemberInvitation(
        string $email,
        User $invitedBy,
        Tenant $tenant,
        ?Group $group = null,
        ?TenantRole $tenantRole = null
    ): Invitation {
        return Invitation::create([
            'tenant_id' => $tenant->id,
            'group_id' => $group?->id,
            'invited_by_id' => $invitedBy->id,
            'tenant_role_id' => $tenantRole?->id,
            'email' => $email,
            'token' => Str::random(60),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function acceptInvitation(string $token, string $password, ?string $displayName = null): array
    {
        /**
         * @var Invitation
         */
        $invitation = Invitation::with('tenant')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return DB::transaction(function () use ($invitation, $password, $displayName) {
            $username = $this->tenantUserService->generateUniqueTenantUsername(
                $displayName ?? $invitation->email,
                $invitation->tenant
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
                'user' => $user/*->load(['tenant', 'tenantRole'])*/,
                'invitation' => $invitation/*->load('invitedBy')*/,
            ];
        });
    }
}
