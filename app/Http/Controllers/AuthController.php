<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\RegistrationRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Support\Flash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

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
                $credentials['tenant_slug'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        return redirect()->route('auth.login')->with(
            'success',
            'Your registration request has been submitted. An admin will review it before you can sign in.',
        );
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function showTrack()
    {
        return view('auth.track');
    }

    public function track(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $registration = RegistrationRequest::where('email', $data['email'])
            ->latest()
            ->first();

        if ($registration === null) {
            return back()->withErrors([
                'email' => 'No registration request was found for this email.',
            ])->withInput();
        }

        return view('auth.track', [
            'tracked' => $registration,
        ]);
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
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        /** @var User $user */
        $user = Auth::user();
        $redirect = $user->isOwner()
            ? route('owner.index')
            : route('groups.index');

        if ($request->wantsJson()) {
            return response()->json([
                'redirect' => redirect()->intended($redirect)->getTargetUrl(),
                'user_id' => $user->id,
                'e2ee_backup' => $user->e2ee_private_backup,
                'csrf_token' => csrf_token(),
            ]);
        }

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

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => __('passwords.user')])->withInput();
        }

        $token = Password::broker()->createToken($user);

        return Flash::back(
            'Password reset link created. Share the link below with the user.',
            [[
                'label' => 'Password reset link',
                'url' => route('password.reset', [
                    'token' => $token,
                    'email' => $request->email,
                ]),
            ]],
        );
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->get('email'),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('auth.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->withInput();
    }
}
