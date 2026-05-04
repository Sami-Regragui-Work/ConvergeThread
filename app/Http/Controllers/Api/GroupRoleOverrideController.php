<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGroupRoleOverrideRequest;
use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\TenantRole;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GroupRoleOverrideController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Group $group): JsonResponse
    {
        Gate::authorize('manageRoleOverrides', $group);

        $overrides = $group->groupRoleOverrides()->with('tenantRole')->get();
        return response()->json($overrides);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupRoleOverrideRequest $request, Group $group): JsonResponse
    {
        $cridentials = $request->validated();
        Gate::authorize('manageRoleOverrides', $group);

        $tenantRole = TenantRole::findOrFail($cridentials['tenant_role_id']);
        $override = $this->roleService->createGroupRoleOverride(
            $group,
            $tenantRole,
            $cridentials['permissions'] ?? null
        );

        return response()->json($override->load('tenantRole'), 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GroupRoleOverride $groupRoleOverride): JsonResponse
    {
        Gate::authorize('manageRoleOverrides', $groupRoleOverride->group);
        
        $this->roleService->deleteGroupRoleOverride($groupRoleOverride);
        return response()->json(null, 204);
    }
}
