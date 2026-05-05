<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRoleOverrideRequest;
use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\TenantRole;
use App\Services\RoleService;
use Illuminate\Support\Facades\Gate;

class GroupRoleOverrideController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Group $group)
    {
        Gate::authorize('viewAny', [GroupRoleOverride::class, $group]);

        $overrides = $group->groupRoleOverrides()->with('tenantRole')->get();

        return view('group_role_overrides.index', compact('overrides', 'group'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupRoleOverrideRequest $request, Group $group)
    {
        $cridentials = $request->validated();
        Gate::authorize('create', [GroupRoleOverride::class, $group]);

        $tenantRole = TenantRole::findOrFail($cridentials['tenant_role_id']);

        $this->roleService->createGroupRoleOverride(
            $group,
            $tenantRole,
            $cridentials['permissions'] ?? null
        );

        return redirect()
            ->route('groups.role-overrides.index', $group)
            ->with('success', 'Group role override created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group, GroupRoleOverride $groupRoleOverride)
    {
        Gate::authorize('delete', [GroupRoleOverride::class, $group]);

        $this->roleService->deleteGroupRoleOverride($groupRoleOverride);

        return redirect()
            ->route('groups.role-overrides.index', $group)
            ->with('success', 'Group role override deleted successfully.');
    }
}
