<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function register(
        string $email,
        string $password,
        string $username,
        ?string $displayName,
        Tenant $tenant
    ): array {
        Auth::shouldUse('api');

        $userData = [
            'email' => $email,
            'password' => Hash::make($password),
            'username' => Str::slug($username),
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

        if (!$token= Auth::attempt(compact('email', 'password'))) {
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
