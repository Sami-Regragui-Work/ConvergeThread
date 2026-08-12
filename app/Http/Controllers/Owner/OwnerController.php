<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Duo;
use App\Models\Group;
use App\Models\RegistrationRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SortsLists;
use Illuminate\Http\Request;

class OwnerController extends Controller
{
    use SortsLists;

    public function index(Request $request)
    {
        [$usersSort, $usersDir] = $this->resolveSort(
            $request->query(),
            ['id', 'created_at', 'display_name', 'email', 'banned'],
            'id',
            'asc',
            'usort',
            'udir',
        );

        $users = User::query()
            ->with(['tenant', 'tenantRole', 'bannedBy'])
            ->orderBy(
                $usersSort === 'banned' ? 'banned_by_id' : $usersSort,
                $usersSort === 'banned' && $usersDir === 'asc' ? 'desc' : $usersDir,
            )
            ->orderBy('id')
            ->get();

        [$tenantsSort, $tenantsDir] = $this->resolveSort(
            $request->query(),
            ['id', 'created_at', 'name', 'users_count'],
            'id',
            'asc',
            'tsort',
            'tdir',
        );

        $tenants = Tenant::query()
            ->with(['closure.closedBy'])
            ->withCount(['users', 'groups', 'tenantRoles'])
            ->orderBy($tenantsSort, $tenantsDir)
            ->orderBy('id')
            ->get();

        [$groupsSort, $groupsDir] = $this->resolveSort(
            $request->query(),
            ['id', 'created_at', 'name', 'members_count'],
            'id',
            'asc',
            'gsort',
            'gdir',
        );

        $groups = Group::query()
            ->with(['tenant', 'creator', 'members'])
            ->withCount('members')
            ->orderBy($groupsSort, $groupsDir)
            ->orderBy('id')
            ->get();

        $duos = Duo::query()
            ->with(['group', 'user1', 'user2'])
            ->orderBy('id')
            ->get();

        $pendingRegistrations = RegistrationRequest::query()
            ->where('status', 'pending')
            ->with('tenant')
            ->latest()
            ->get();

        $stats = [
            'users_count' => $users->count(),
            'tenants_count' => $tenants->count(),
            'closed_tenants_count' => $tenants->filter(fn (Tenant $t) => $t->isClosed())->count(),
            'groups_count' => $groups->count(),
            'duos_count' => $duos->count(),
            'banned_users_count' => $users->whereNotNull('banned_by_id')->count(),
            'pending_registrations_count' => $pendingRegistrations->count(),
        ];

        return view('owner.index', compact(
            'users',
            'tenants',
            'groups',
            'duos',
            'pendingRegistrations',
            'stats',
        ));
    }
}
