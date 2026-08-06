<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRoleRequest;
use App\Http\Requests\UpdateTenantRoleRequest;
use App\Models\TenantRole;
use App\Services\RoleService;
use App\Support\Permissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TenantRoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService)
    {
    }

    public function index()
    {
        $tenantId = Auth::user()->tenant_id;
        Gate::authorize('viewAny', TenantRole::class);

        $roles = TenantRole::where('tenant_id', $tenantId)
            ->orWhere('is_system', true)
            ->orderBy('name')
            ->get();

        return view('tenant-roles.index', compact('roles'));
    }

    public function create()
    {
        Gate::authorize('create', TenantRole::class);

        return view('tenant-roles.create', [
            'permissions' => Permissions::all(),
        ]);
    }

    public function store(StoreTenantRoleRequest $request)
    {
        $credentials = $request->validated();
        Gate::authorize('create', TenantRole::class);

        $tenant = Auth::user()->tenant;

        $this->roleService->createTenantRole(
            $tenant,
            $credentials['name'],
            $credentials['permissions'],
            $credentials['color'],
        );

        return redirect()
            ->route('tenant-roles.index')
            ->with('success', 'Tenant role created successfully.');
    }

    public function edit(TenantRole $tenantRole)
    {
        Gate::authorize('update', $tenantRole);

        return view('tenant-roles.edit', [
            'role' => $tenantRole,
            'permissions' => Permissions::all(),
        ]);
    }

    public function update(UpdateTenantRoleRequest $request, TenantRole $tenantRole)
    {
        $credentials = $request->validated();
        Gate::authorize('update', $tenantRole);

        $this->roleService->updateTenantRole(
            $tenantRole,
            $credentials['name'],
            $credentials['permissions'] ?? $tenantRole->permissions ?? [],
            $credentials['color'],
        );

        return redirect()
            ->route('tenant-roles.index')
            ->with('success', 'Tenant role updated successfully.');
    }

    public function destroy(TenantRole $tenantRole)
    {
        Gate::authorize('delete', $tenantRole);

        $this->roleService->deleteTenantRole($tenantRole);

        return redirect()
            ->route('tenant-roles.index')
            ->with('success', 'Tenant role deleted successfully.');
    }
}
