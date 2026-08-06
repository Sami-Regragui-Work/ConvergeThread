<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class WorkspaceSyncController extends Controller
{
    public function poll()
    {
        $user = Auth::user();

        return response()->json([
            'unread_notifications' => $user->unreadNotifications()->count(),
            'groups_updated_at' => Group::query()
                ->where('tenant_id', $user->tenant_id)
                ->max('updated_at'),
            'users_updated_at' => User::query()
                ->where('tenant_id', $user->tenant_id)
                ->max('updated_at'),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
