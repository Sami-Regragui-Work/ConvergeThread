<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Duo;
use App\Models\Group;
use App\Models\Tenant;
use App\Models\User;

class OwnerController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $query = $request->input('q');
        $query = is_string($query) ? trim($query) : '';

        $usersQuery = User::query()
            ->with(['tenant', 'tenantRole', 'bannedBy'])
            ->orderBy('id');

        $tenantsQuery = Tenant::query()
            ->with(['closure.closedBy'])
            ->withCount(['users', 'groups', 'tenantRoles'])
            ->orderBy('id');

        $groupsQuery = Group::query()
            ->with(['tenant', 'creator', 'members'])
            ->withCount('members')
            ->orderBy('id');

        if ($query !== '') {
            $usersQuery->where(function ($q) use ($query) {
                $q->where('email', 'like', "%{$query}%")
                    ->orWhere('username', 'like', "%{$query}%")
                    ->orWhere('display_name', 'like', "%{$query}%");
            });

            $tenantsQuery->where(function ($q) use ($query) {
                $q->where('slug', 'like', "%{$query}%")
                    ->orWhere('admin_email', 'like', "%{$query}%");
            });

            $groupsQuery->where('name', 'like', "%{$query}%");
        }

        $users = $usersQuery->get();
        $tenants = $tenantsQuery->get();
        $groups = $groupsQuery->get();

        $duos = Duo::query()
            ->with(['group', 'user1', 'user2'])
            ->when($query !== '', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%");
            })
            ->orderBy('id')
            ->get();

        $stats = [
            'users_count' => $users->count(),
            'tenants_count' => $tenants->count(),
            'closed_tenants_count' => $tenants->filter(fn (Tenant $t) => $t->isClosed())->count(),
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
