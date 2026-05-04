<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRoleRequest;
use App\Models\TenantRole;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TenantRoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        Gate::authorize('viewAny', TenantRole::class);

        $roles = TenantRole::where('tenant_id', $tenantId)->get();
        return response()->json($roles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTenantRoleRequest $request): JsonResponse
    {
        $cridentials = $request->validated();
        Gate::authorize('create', TenantRole::class);

        $tenant = $request->user()->tenant;

        $role = $this->roleService->createTenantRole(
            $tenant,
            $cridentials['name'],
            $cridentials['permissions']
        );
        return response()->json($role, 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TenantRole $tenantRole): JsonResponse
    {
        Gate::authorize('delete', $tenantRole);
        
        $this->roleService->deleteTenantRole($tenantRole);
        return response()->json(null, 204);
    }
}
