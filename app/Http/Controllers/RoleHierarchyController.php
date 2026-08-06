<?php

namespace App\Http\Controllers;

use App\Models\RoleHierarchy;
use App\Models\RoleHierarchyLevel;
use App\Models\User;
use App\Services\RoleHierarchyService;
use App\Support\Permissions;
use App\Support\WorkspaceSync;
use App\Services\TenantPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleHierarchyController extends Controller
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissionService,
        private readonly RoleHierarchyService $roleHierarchyService,
    ) {
    }

    public function index()
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);

        $hierarchies = RoleHierarchy::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['levels.members:id,display_name,username,email'])
            ->orderBy('name')
            ->get();

        $members = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('banned_by_id')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'email']);

        return view('hierarchies.index', compact('hierarchies', 'members'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);

        $data = $request->validate(['name' => 'required|string|max:100']);

        $hierarchy = RoleHierarchy::create([
            'tenant_id' => $user->tenant_id,
            'name' => $data['name'],
        ]);

        RoleHierarchyLevel::create([
            'role_hierarchy_id' => $hierarchy->id,
            'level' => 0,
            'label' => 'Top level',
        ]);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Hierarchy created.');
    }

    public function addLevel(RoleHierarchy $hierarchy)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        abort_unless((int) $hierarchy->tenant_id === (int) $user->tenant_id, 404);

        $next = (int) $hierarchy->levels()->max('level') + 1;

        RoleHierarchyLevel::create([
            'role_hierarchy_id' => $hierarchy->id,
            'level' => $next,
            'label' => 'Level '.$next,
        ]);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Level added.');
    }

    public function syncLevelMembers(Request $request, RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $level->load('hierarchy');
        abort_unless((int) $level->hierarchy->tenant_id === (int) $user->tenant_id, 404);

        $ids = $request->validate([
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ])['user_ids'] ?? [];

        $validIds = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $ids)
            ->pluck('id');

        $level->members()->sync($validIds);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies', 'members']);

        return back()->with('success', 'Level members updated.');
    }

    public function destroyLevel(RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $level->load('hierarchy');
        abort_unless((int) $level->hierarchy->tenant_id === (int) $user->tenant_id, 404);
        abort_unless((int) $level->level > 0, 422, 'The top level cannot be removed.');

        $level->delete();

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Level removed.');
    }

    public function destroy(RoleHierarchy $hierarchy)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        abort_unless((int) $hierarchy->tenant_id === (int) $user->tenant_id, 404);

        $hierarchy->delete();

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Hierarchy deleted.');
    }

    private function canManage(User $user): bool
    {
        return $this->tenantPermissionService->hasPermission($user, Permissions::TENANT_ROLES_VIEW)
            || $this->roleHierarchyService->isTenantFounder($user);
    }
}
