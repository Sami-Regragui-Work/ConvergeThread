<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
// use Tymon\JWTAuth\Facades\JWTAuth;

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

        $user = User::create([
            'email' => $email,
            'password' => Hash::make($password),
            'username' => $username,
            'display_name' => $displayName,
            'tenant_id' => $tenant->id,
        ]);

        Auth::login($user);

        session()->regenerate();

        return $user;
    }

    public function login(string $email, string $password): User
    {
        if (!Auth::attempt(compact('email', 'password'), false)) {
            throw new \Exception('Invalid cridentials', 401);
        }

        $user = Auth::user();

        if ($user->banned_by_id !== null) {
            Auth::logout();
            throw new \Exception('Banned account', 403);
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
