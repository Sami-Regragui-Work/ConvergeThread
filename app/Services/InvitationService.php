<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(
        private readonly TenantUserService $tenantUserService,
    ) {
    }

    public function createAdminInvitation(string $email, User $owner): Invitation
    {
        if (!$owner->isOwner()) {
            throw ValidationException::withMessages([
                'email' => 'Only the owner can create admin invitations.',
            ]);
        }

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists.',
            ]);
        }

        $tenant = Tenant::findOrFail($owner->tenant_id);

        if ($tenant->id !== 1) {
            throw ValidationException::withMessages([
                'email' => 'Admin invitations are only allowed for the owner tenant.',
            ]);
        }

        $this->expireOld($email);

        // ensures none duplicate owner
        if (User::where('tenant_id', $tenant->id)->exists()) {
            throw ValidationException::withMessages([
                'token' => 'The owner account already exists.',
            ]);
        }

        return Invitation::create([
            'tenant_id' => $tenant->id,
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
        ?TenantRole $tenantRole = null,
    ): Invitation {
        if ($invitedBy->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'email' => 'Inviter does not belong to this tenant.',
            ]);
        }

        if ($group && $group->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'email' => 'Selected group does not belong to this tenant.',
            ]);
        }

        if ($tenantRole && $tenantRole->tenant_id !== $tenant->id) {
            throw ValidationException::withMessages([
                'email' => 'Selected tenant role does not belong to this tenant.',
            ]);
        }

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists.',
            ]);
        }

        $this->expireOld($email);

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
        $invitation = Invitation::with(['tenant', 'group', 'tenantRole', 'invitedBy'])
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return DB::transaction(function () use ($invitation, $password, $displayName) {

            $this->checkInvite($invitation);

            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'A user with this email already exists.',
                ]);
            }

            if ($invitation->tenant_id === 1 && User::where('tenant_id', 1)->exists()) {
                throw ValidationException::withMessages([
                    'token' => 'The owner account has already been created.',
                ]);
            }

            $username = $this->tenantUserService->generateUniqueTenantUsername(
                $displayName ?: Str::before($invitation->email, '@'),
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

            if ($invitation->group_id) {
                GroupMember::updateOrCreate(
                    [
                        'group_id' => $invitation->group_id,
                        'user_id' => $user->id,
                    ],
                    [
                        'group_role_override_id' => null,
                        'permissions' => null,
                        'left_at' => null,
                    ]
                );
            }

            $invitation->update([
                'accepted_at' => now(),
            ]);

            return [
                'user' => $user,
                'invitation' => $invitation->fresh(['tenant', 'group', 'tenantRole', 'invitedBy']),
            ];
        });
    }

    private function expireOld(string $email): void
    {
        Invitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->update([
                'expires_at' => now(),
            ]);
    }

    private function checkInvite(Invitation $invitation): void
    {
        if (!$invitation->tenant) {
            throw ValidationException::withMessages([
                'token' => 'Invitation tenant was not found.',
            ]);
        }

        if ($invitation->group && $invitation->group->tenant_id !== $invitation->tenant_id) {
            throw ValidationException::withMessages([
                'token' => 'Invitation group does not belong to the invitation tenant.',
            ]);
        }

        if ($invitation->tenantRole && $invitation->tenantRole->tenant_id !== $invitation->tenant_id) {
            throw ValidationException::withMessages([
                'token' => 'Invitation role does not belong to the invitation tenant.',
            ]);
        }
    }

    public function findOpen(string $token): Invitation
    {
        $invitation = Invitation::where('token', $token)
            ->with(['tenant', 'group', 'tenantRole', 'invitedBy'])
            ->firstOrFail();

        if ($invitation->accepted_at) {
            throw ValidationException::withMessages([
                'token' => 'Invitation already accepted.',
            ]);
        }

        if ($invitation->expires_at && $invitation->expires_at < now()) {
            throw ValidationException::withMessages([
                'token' => 'Invitation expired.',
            ]);
        }

        return $invitation;
    }
}
