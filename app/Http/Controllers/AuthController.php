<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        $credentials = $request->validated();

        try {
            $this->authService->register(
                $credentials['email'],
                $credentials['password'],
                $credentials['display_name'] ?? null,
                $credentials['tenant_slug']
            );
        } catch (\Exception $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('groups.index')->with('success', 'Welcome!');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        try {
            $this->authService->login(
                $credentials['email'],
                $credentials['password']
            );
        } catch (\Exception $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        /** @var User $user */
        $user = Auth::user();
        if ($user->isOwner()) {
            return redirect()->intended(route('owner.index'));
        }

        return redirect()->intended(route('groups.index'));
    }

    public function logout()
    {
        $this->authService->logout();

        return redirect()->route('auth.login');
    }
}
