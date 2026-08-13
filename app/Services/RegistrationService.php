<?php

namespace App\Services;

use App\Models\RegistrationRequest;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\RegistrationApprovalRequiredNotification;
use App\Support\Permissions;
use App\Support\WorkspaceSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(
        private readonly TenantUserService $tenantUserService,
        private readonly TenantPermissionService $tenantPermissionService,
    ) {}

    public function submit(
        string $email,
        string $password,
        ?string $displayName,
        ?string $tenantSlug,
        ?string $tenantName = null,
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
            'tenant_name' => $tenant?->name ?? $tenantName,
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

            WorkspaceSync::bump($tenant->id, ['users', 'members', 'registrations']);
        } else {
            $owner = User::where('tenant_id', 1)->first();

            if ($owner) {
                $owner->notify(new RegistrationApprovalRequiredNotification($request));
            }

            WorkspaceSync::bump(null, ['users', 'tenants', 'registrations']);
        }

        return $request;
    }

    public function approve(User $actor, RegistrationRequest $request, ?int $tenantId = null): void
    {
        abort_unless($request->isPending(), 404);

        $tenant = $this->resolveTenant($actor, $request, $tenantId);

        if ($tenant->isClosed()) {
            throw new \InvalidArgumentException('That workspace is closed.');
        }

        if (User::where('email', $request->email)->exists()) {
            throw new \InvalidArgumentException('A user with this email already exists.');
        }

        DB::transaction(function () use ($actor, $request, $tenant) {
            $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
            $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');
            $username = $this->tenantUserService->generateUniqueTenantUsername(
                $request->display_name ?? Str::before($request->email, '@'),
                $tenant,
            );

            $roleId = $tenant->wasRecentlyCreated
                ? $adminRoleId
                : $memberRoleId;

            $user = User::create([
                'email' => $request->email,
                'password' => $request->password,
                'username' => $username,
                'display_name' => $request->display_name,
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $roleId,
            ]);

            $request->update([
                'tenant_id' => $tenant->id,
                'status' => 'approved',
                'resolved_at' => now(),
                'resolved_by_id' => $actor->id,
            ]);

            if ($tenant->wasRecentlyCreated) {
                $tenant->update(['admin_email' => $user->email]);
            }

            WorkspaceSync::bump($tenant->id, ['users', 'tenants', 'members']);
        });
    }

    public function reject(User $actor, RegistrationRequest $request): void
    {
        abort_unless($request->isPending(), 404);

        if (! $actor->isOwner()) {
            abort_unless((int) $request->tenant_id === (int) $actor->tenant_id, 404);
            abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($actor), 403);
        }

        $request->update([
            'status' => 'rejected',
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
        ]);

        if ($request->tenant_id !== null) {
            WorkspaceSync::bump($request->tenant_id, ['users', 'members']);
        }
    }

    private function resolveTenant(User $actor, RegistrationRequest $request, ?int $tenantId = null): Tenant
    {
        if ($request->tenant_id !== null) {
            if ($actor->isOwner()) {
                return Tenant::findOrFail((int) $request->tenant_id);
            }

            abort_unless((int) $request->tenant_id === (int) $actor->tenant_id, 404);
            abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($actor), 403);

            return Tenant::findOrFail((int) $request->tenant_id);
        }

        abort_unless($actor->isOwner(), 403);

        if ($tenantId !== null) {
            return Tenant::findOrFail($tenantId);
        }

        $slug = $this->generateUniqueSlug($request->tenant_slug ?? Str::slug($request->tenant_name ?? 'workspace', '_'));

        return Tenant::create([
            'slug' => $slug,
            'name' => $request->tenant_name ?? $request->tenant_slug ?? 'Workspace',
            'admin_email' => $request->email,
        ]);
    }

    private function generateUniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'_'.$i++;
        }

        return $slug;
    }
}
