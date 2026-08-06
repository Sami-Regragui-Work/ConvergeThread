<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\WorkspaceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerUserController extends Controller
{
    public function ban(Request $request, User $user)
    {
        $owner = Auth::user();

        if ($user->isOwner()) {
            return back()->withErrors(['user' => 'The owner account cannot be banned.']);
        }

        if ($user->banned_by_id !== null) {
            return back()->with('success', 'User is already banned.');
        }

        $user->update(['banned_by_id' => $owner->id]);
        WorkspaceSync::bump($user->tenant_id, ['users']);

        return back()->with('success', 'User banned successfully.');
    }

    public function unban(User $user)
    {
        if ($user->banned_by_id === null) {
            return back()->with('success', 'User is not banned.');
        }

        $user->update(['banned_by_id' => null]);
        WorkspaceSync::bump($user->tenant_id, ['users']);

        return back()->with('success', 'User unbanned successfully.');
    }
}
