<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRoleOverrideRequest;
use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\TenantRole;
use App\Services\RoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GroupRoleOverrideController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    public function index(Group $group)
    {
        Gate::authorize('viewAny', [GroupRoleOverride::class, $group]);

        $overrides = $group->groupRoleOverrides()->with('tenantRole')->get();
        $tenantRoles = TenantRole::where(function ($query) use ($group) {
            $query->where('tenant_id', $group->tenant_id)
                ->orWhere('is_system', true);
        })->orderBy('name')->get();

        return view('groups.role-overrides.index', compact('overrides', 'group', 'tenantRoles'));
    }

    public function store(StoreGroupRoleOverrideRequest $request, Group $group)
    {
        $credentials = $request->validated();
        Gate::authorize('create', [GroupRoleOverride::class, $group]);

        $tenantRole = TenantRole::findOrFail($credentials['tenant_role_id']);

        $this->roleService->createGroupRoleOverride(
            $group,
            $tenantRole,
            $credentials['permissions'] ?? null
        );

        return redirect()
            ->route('groups.role-overrides.index', $group)
            ->with('success', 'Group role override created successfully.');
    }

    public function destroy(Group $group, GroupRoleOverride $groupRoleOverride)
    {
        Gate::authorize('delete', [GroupRoleOverride::class, $group, $groupRoleOverride]);

        $this->roleService->deleteGroupRoleOverride($groupRoleOverride);

        return redirect()
            ->route('groups.role-overrides.index', $group)
            ->with('success', 'Group role override deleted successfully.');
    }
}
