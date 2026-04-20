<?php

namespace App\Services;

use App\Models\User;
use App\Services\TenantUserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

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
    ): array {
        Auth::shouldUse('api');

        $tenant = $this->tenantUserService->findTenantBySlug($tenantSlug);

        $username = $this->tenantUserService->generateUniqueTenantUsername(
            $displayName ?? explode('@', $email)[0],
            $tenant
        );

        $userData = [
            'email' => $email,
            'password' => Hash::make($password),
            'username' => $username,
            'display_name' => $displayName,
            'tenant_id' => $tenant->id,
        ];

        $user = User::create($userData);

        Auth::login($user);

        $token = JWTAuth::fromUser($user);

        return $this->getAuthResponse($token, $user);
    }

    public function login(string $email, string $password): array
    {
        Auth::shouldUse('api');
        /** @var string $token */
        if (!$token = Auth::attempt(compact('email', 'password'))) {
            throw new \Exception('Invalid credentials', 401);
        }

        $user = Auth::user();
        if ($user->is_banned) {
            Auth::logout();
            throw new \Exception('Banned account', 403);
        }

        return $this->getAuthResponse($token, $user);
    }

    public function refresh(): array
    {
        Auth::shouldUse('api');

        $token = Auth::refresh();
        return [
            'token' => $token,
            'token_type' => 'bearer',
        ];
    }

    public function logout(): void
    {
        Auth::shouldUse('api');
        Auth::logout();
    }

    private function getAuthResponse(string $token, User $user): array
    {
        return [
            'user' => $user->load('tenant'),
            'token' => $token,
            'token_type' => 'bearer',
        ];
    }
}
