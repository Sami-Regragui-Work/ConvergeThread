<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Duo;
use App\Models\Group;
use App\Models\Tenant;
use App\Models\User;

class OwnerController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with(['tenant', 'tenantRole', 'bannedBy'])
            ->orderBy('id')
            ->get();

        $tenants = Tenant::query()
            ->with(['closedBy'])
            ->withCount(['users', 'groups', 'tenantRoles'])
            ->orderBy('id')
            ->get();

        $groups = Group::query()
            ->with(['tenant', 'creator', 'members'])
            ->withCount('members')
            ->orderBy('id')
            ->get();

        $duos = Duo::query()
            ->with(['group', 'user1', 'user2'])
            ->orderBy('id')
            ->get();

        $stats = [
            'users_count' => $users->count(),
            'tenants_count' => $tenants->count(),
            'closed_tenants_count' => $tenants->whereNotNull('closed_by_id')->count(),
            'groups_count' => $groups->count(),
            'duos_count' => $duos->count(),
            'banned_users_count' => $users->whereNotNull('banned_by_id')->count(),
        ];

        return view('owner.index', compact(
            'users',
            'tenants',
            'groups',
            'duos',
            'stats',
        ));
    }
}
