<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile.index', [
            'user' => Auth::user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:100'],
            'username' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_.-]+$/',
                Rule::unique('users', 'username')->where('tenant_id', $user->tenant_id)->ignore($user->id),
            ],
            'avatar_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $user->update([
            'display_name' => $data['display_name'] ?: null,
            'username' => $data['username'] ?: $user->username,
            'avatar_color' => $data['avatar_color'] ?: null,
        ]);

        WorkspaceSync::bump($user->tenant_id, ['users']);

        return back()->with('success', 'Profile updated.');
    }

    public function changeEmail(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'string'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->update(['email' => $data['email']]);

        return back()->with('success', 'Email address updated.');
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        return back()->with('success', 'Password updated.');
    }

    public function destroy(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        abort_if($user->isOwner(), 403, 'The owner account cannot be deleted.');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $tenantId = $user->tenant_id;

        $user->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        WorkspaceSync::bump($tenantId, ['users', 'groups', 'members']);

        return redirect()->route('auth.login')->with(
            'success',
            'Your account has been deleted. Your messages and call history were kept.',
        );
    }
}
