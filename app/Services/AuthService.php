<?php

namespace App\Services;

use App\Models\RegistrationRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function __construct(private readonly RegistrationService $registrationService) {}

    public function register(
        string $email,
        string $password,
        ?string $displayName,
        ?string $tenantSlug,
        ?string $tenantName = null,
    ): RegistrationRequest {
        return $this->registrationService->submit(
            $email,
            $password,
            $displayName,
            $tenantSlug,
            $tenantName,
        );
    }

    public function login(string $email, string $password): User
    {
        if (! Auth::attempt(compact('email', 'password'), false)) {
            $this->throwRegistrationStatusMessage($email);

            throw new \Exception('Invalid credentials', 401);
        }

        $user = Auth::user();

        if (! $user instanceof User) {
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

    private function throwRegistrationStatusMessage(string $email): void
    {
        $hasUser = User::where('email', $email)->exists();

        if ($hasUser) {
            return;
        }

        $registration = RegistrationRequest::where('email', $email)->latest()->first();

        if ($registration === null) {
            return;
        }

        if ($registration->isExpired()) {
            throw new \Exception('Your registration request has expired. Please register again.', 401);
        }

        if ($registration->isRejected()) {
            throw new \Exception('Your registration request was declined. Please contact your workspace admin if you believe this is a mistake.', 401);
        }

        if ($registration->isPending()) {
            throw new \Exception('Your registration request is still pending approval. An admin has not accepted you yet — track its status below.', 401);
        }
    }

    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
