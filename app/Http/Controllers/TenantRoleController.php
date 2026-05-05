<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRoleRequest;
use App\Models\TenantRole;
use App\Services\RoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TenantRoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        Gate::authorize('viewAny', TenantRole::class);

        $roles = TenantRole::where('tenant_id', $tenantId)->get();

        return view('tenant_roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a newly created resource.
     */
    public function create()
    {
        Gate::authorize('create', TenantRole::class);

        return view('tenant_roles.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTenantRoleRequest $request)
    {
        $cridentials = $request->validated();
        Gate::authorize('create', TenantRole::class);

        $tenant = Auth::user()->tenant;

        $this->roleService->createTenantRole(
            $tenant,
            $cridentials['name'],
            $cridentials['permissions']
        );

        return redirect()
            ->route('tenant-roles.index')
            ->with('success', 'Tenant role created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TenantRole $tenantRole)
    {
        Gate::authorize('delete', $tenantRole);

        $this->roleService->deleteTenantRole($tenantRole);

        return redirect()
            ->route('tenant-roles.index')
            ->with('success', 'Tenant role deleted successfully.');
    }
}
