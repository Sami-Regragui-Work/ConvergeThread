<?php

namespace App\Services;

use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly TenantUserService $tenantUserService)
    {
    }
    public function register(
        string $email,
        string $password,
        ?string $displayName,
        string $tenantSlug
    ): User {
        $tenant = $this->tenantUserService->findTenantBySlug($tenantSlug);

        $username = $this->tenantUserService->generateUniqueTenantUsername(
            $displayName ?? explode('@', $email)[0],
            $tenant
        );

        if ($tenant->isClosed()) {
            throw new \Exception('This workspace is closed.', 403);
        }

        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $user = User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'username' => $username,
            'display_name' => $displayName,
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        Auth::login($user);

        session()->regenerate();

        return $user;
    }

    public function login(string $email, string $password): User
    {
        if (!Auth::attempt(compact('email', 'password'), false)) {
            throw new \Exception('Invalid credentials', 401);
        }

        $user = Auth::user();

        if (!$user instanceof User) {
            Auth::logout();
            throw new \Exception('Invalid credentials', 401);
        }

        if ($user->banned_by_id !== null) {
            Auth::logout();
            throw new \Exception('Banned account', 403);
        }

        $user->load('tenant');

        if ($user->tenant && $user->tenant->isClosed()) {
            Auth::logout();
            throw new \Exception('This workspace is closed.', 403);
        }

        session()->regenerate();

        return $user;
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
