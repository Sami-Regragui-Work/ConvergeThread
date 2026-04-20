<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\TenantRole;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $overrides = $group->groupRoleOverrides()->with('tenantRole')->get();
        return response()->json($overrides);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Group $group): JsonResponse
    {
        $tenantRole = TenantRole::findOrFail($request->tenant_role_id);
        $override = $this->roleService->createGroupRoleOverride(
            $group,
            $tenantRole,
            $request->permissions
        );

        return response()->json($override->load('tenantRole'), 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GroupRoleOverride $groupRoleOverride): JsonResponse
    {
        $this->roleService->deleteGroupRoleOverride($groupRoleOverride);
        return response()->json(null, 204);
    }
}
