<?php

namespace App\Services;

use App\Models\RegistrationRequest;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\RegistrationApprovalRequiredNotification;
use App\Support\Permissions;
use App\Support\WorkspaceSync;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(
        private readonly TenantUserService $tenantUserService,
        private readonly TenantPermissionService $tenantPermissionService,
    ) {
    }

    public function submit(
        string $email,
        string $password,
        ?string $displayName,
        ?string $tenantSlug,
    ): RegistrationRequest {
        $tenant = $tenantSlug !== null && $tenantSlug !== ''
            ? Tenant::where('slug', $tenantSlug)->first()
            : null;

        if ($tenant && $tenant->isClosed()) {
            throw new \InvalidArgumentException('This workspace is closed.');
        }

        if (RegistrationRequest::where('email', $email)->where('status', 'pending')->exists()) {
            throw new \InvalidArgumentException('You already have a pending registration request for this email.');
        }

        $request = RegistrationRequest::create([
            'email' => $email,
            'password' => Hash::make($password),
            'tenant_slug' => $tenant?->slug ?? $tenantSlug,
            'tenant_id' => $tenant?->id,
            'display_name' => $displayName,
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
        ]);

        if ($tenant) {
            User::query()
                ->where('tenant_id', $tenant->id)
                ->whereNull('banned_by_id')
                ->get()
                ->filter(fn (User $u) => $this->tenantPermissionService->hasPermission($u, Permissions::INVITATIONS_CREATE_MEMBER))
                ->each(fn (User $approver) => $approver->notify(new RegistrationApprovalRequiredNotification($request)));
        } else {
            $owner = User::where('tenant_id', 1)->first();

            if ($owner) {
                $owner->notify(new RegistrationApprovalRequiredNotification($request));
            }
        }

        return $request;
    }

    public function approve(User $actor, RegistrationRequest $request, ?int $tenantId = null): void
    {
        abort_unless($request->isPending(), 404);

        $tenant = Tenant::findOrFail($this->authorize($actor, $request, $tenantId));

        if ($tenant->isClosed()) {
            throw new \InvalidArgumentException('That workspace is closed.');
        }

        if (User::where('email', $request->email)->exists()) {
            throw new \InvalidArgumentException('A user with this email already exists.');
        }

        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');
        $username = $this->tenantUserService->generateUniqueTenantUsername(
            $request->display_name ?? Str::before($request->email, '@'),
            $tenant,
        );

        User::create([
            'email' => $request->email,
            'password' => $request->password,
            'username' => $username,
            'display_name' => $request->display_name,
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $request->update([
            'status' => 'approved',
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
        ]);

        WorkspaceSync::bump($tenant->id, ['users', 'members']);
    }

    public function reject(User $actor, RegistrationRequest $request): void
    {
        abort_unless($request->isPending(), 404);

        $this->authorize($actor, $request);

        $request->update([
            'status' => 'rejected',
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
        ]);

        if ($request->tenant_id !== null) {
            WorkspaceSync::bump($request->tenant_id, ['users', 'members']);
        }
    }

    private function authorize(User $actor, RegistrationRequest $request, ?int $tenantId = null): int
    {
        $isOwner = $actor->isOwner();

        if ($request->tenant_id === null) {
            abort_unless($isOwner && $tenantId !== null, 403);

            return $tenantId;
        }

        if ($isOwner) {
            return (int) $request->tenant_id;
        }

        abort_unless((int) $request->tenant_id === (int) $actor->tenant_id, 404);
        abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($actor), 403);

        return (int) $request->tenant_id;
    }
}
