<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantPermissionService;
use App\Support\DisplayName;
use App\Support\WorkspaceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WorkspaceSettingsController extends Controller
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissionService,
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($user), 403);

        $tenant = $user->tenant;

        return view('workspace.settings', [
            'tenant' => $tenant,
            'title' => 'Workspace settings',
        ]);
    }

    public function updateName(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($user), 403);

        $tenant = $user->tenant;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant->update(['name' => trim($data['name'])]);

        WorkspaceSync::bump($tenant->id, ['workspace', 'members', 'users']);

        return back()->with('status', 'Workspace title updated.');
    }

    public function regenerateSlug(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($user), 403);

        $tenant = $user->tenant;

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
        ]);

        $slug = $this->uniqueSlug(Str::slug($data['slug'], '_'));

        $tenant->update([
            'slug' => $slug,
            'name' => $tenant->name ?: DisplayName::capitalizeFirst(str_replace('_', ' ', $slug)),
        ]);

        WorkspaceSync::bump($tenant->id, ['workspace', 'members', 'users']);

        return back()->with('status', 'Workspace link updated.');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Tenant::where('slug', $slug)->where('id', '!=', auth()->user()->tenant_id)->exists()) {
            $slug = $base.'_'.$i;
            $i++;
        }

        return $slug;
    }
}
