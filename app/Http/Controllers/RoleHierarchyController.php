<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\RoleHierarchy;
use App\Models\RoleHierarchyLevel;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\RoleHierarchyService;
use App\Services\TenantPermissionService;
use App\Support\Permissions;
use App\Support\WorkspaceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleHierarchyController extends Controller
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissionService,
        private readonly RoleHierarchyService $roleHierarchyService,
    ) {}

    public function index()
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);

        $hierarchies = RoleHierarchy::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['levels' => fn ($q) => $q->with(['members:id,display_name,username,email,avatar_color', 'group:id,name', 'role:id,name'])->orderBy('level')->orderBy('id')])
            ->orderBy('name')
            ->get();

        $members = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('banned_by_id')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'email']);

        $groups = Group::query()
            ->where('tenant_id', $user->tenant_id)
            ->withCount('activeMembers')
            ->orderBy('name')
            ->get(['id', 'name']);

        $tenantRoles = TenantRole::query()
            ->forTenant($user->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $memberHierarchies = $hierarchies->where('kind', 'member')->values();
        $roleHierarchies = $hierarchies->where('kind', 'role')->values();

        return view('hierarchies.index', compact(
            'memberHierarchies',
            'roleHierarchies',
            'members',
            'groups',
            'tenantRoles',
        ));
    }

    public function map(RoleHierarchy $hierarchy)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        abort_unless((int) $hierarchy->tenant_id === (int) $user->tenant_id, 404);

        return response()->json($this->roleHierarchyService->mapPayload($hierarchy));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'kind' => 'sometimes|in:member,role',
        ]);

        $kind = $data['kind'] ?? 'member';

        $hierarchy = RoleHierarchy::create([
            'tenant_id' => $user->tenant_id,
            'name' => $data['name'],
            'kind' => $kind,
        ]);

        $this->roleHierarchyService->createNode($hierarchy, null, $kind);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'hierarchy' => [
                    'id' => $hierarchy->id,
                    'name' => $hierarchy->name,
                    'kind' => $hierarchy->kind,
                ],
            ]);
        }

        return back()->with('success', 'Hierarchy created.');
    }

    public function addNode(Request $request, RoleHierarchy $hierarchy)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        abort_unless((int) $hierarchy->tenant_id === (int) $user->tenant_id, 404);

        $data = $request->validate([
            'parent_id' => 'nullable|exists:role_hierarchy_levels,id',
            'kind' => 'required|in:member,group,role',
            'group_id' => 'nullable|exists:groups,id',
            'role_id' => 'nullable|exists:tenant_roles,id',
        ]);

        if ($hierarchy->kind === 'role') {
            abort_unless($data['kind'] === 'role', 422, 'Role hierarchies only hold role nodes.');
        } else {
            abort_unless(in_array($data['kind'], ['member', 'group']), 422, 'Member hierarchies only hold member and group nodes.');
        }

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = RoleHierarchyLevel::findOrFail($data['parent_id']);
            abort_unless((int) $parent->role_hierarchy_id === (int) $hierarchy->id, 422);
        }

        $node = $this->roleHierarchyService->createNode(
            $hierarchy,
            $parent,
            $data['kind'],
            $data['group_id'] ?? null,
            $data['role_id'] ?? null,
        );

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Node added.');
    }

    public function link(Request $request, RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);

        $data = $request->validate([
            'parent_id' => 'required|exists:role_hierarchy_levels,id',
        ]);

        $parent = RoleHierarchyLevel::findOrFail($data['parent_id']);
        abort_unless((int) $parent->role_hierarchy_id === (int) $level->role_hierarchy_id, 422);

        $conflicts = $this->roleHierarchyService->linkNode($level, $parent);

        if ($conflicts) {
            $message = implode(' ', $conflicts);

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['hierarchies' => $message]);
        }

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Node linked under its new parent.');
    }

    public function addParent(RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);

        $this->roleHierarchyService->addParentLevel($level);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'New parent level inserted above the node.');
    }

    public function syncMembers(Request $request, RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);
        abort_unless($level->kind === 'member' || $level->kind === 'group', 422, 'Only member and group nodes hold members.');

        $ids = $request->validate([
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ])['user_ids'] ?? [];

        $ids = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        $rejected = $this->roleHierarchyService->syncNodeMembers($level, $ids);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies', 'members']);

        if ($rejected) {
            $names = User::whereIn('id', $rejected)->pluck('display_name')->join(', ');
            $message = 'Could not add '.$names.': already sit in an ancestor/descendant position.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['hierarchies' => $message]);
        }

        return back()->with('success', 'Node members updated.');
    }

    public function setGroup(Request $request, RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);

        $data = $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);

        $hierarchy = $level->hierarchy;
        abort_unless($hierarchy->kind === 'member', 422, 'Only member hierarchies can hold group nodes.');

        $group = Group::where('tenant_id', $user->tenant_id)->findOrFail($data['group_id']);

        $rejected = $this->roleHierarchyService->attachGroupToNode($level, $group);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies', 'members']);

        if ($rejected) {
            $names = User::whereIn('id', $rejected)->pluck('display_name')->join(', ');
            $message = 'Could not attach '.$group->name.': '.$names.' already sit in an ancestor/descendant position.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['hierarchies' => $message]);
        }

        return back()->with('success', 'Group node updated.');
    }

    public function setRole(Request $request, RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);

        $data = $request->validate([
            'role_id' => 'required|exists:tenant_roles,id',
        ]);

        $hierarchy = $level->hierarchy;
        abort_unless($hierarchy->kind === 'role', 422, 'Only role hierarchies can hold role nodes.');

        $role = TenantRole::findOrFail($data['role_id']);

        $rejected = $this->roleHierarchyService->attachRoleToNode($level, $role);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        if ($rejected) {
            $message = 'Could not attach role: it already sits in an ancestor/descendant position.';

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['hierarchies' => $message]);
        }

        return back()->with('success', 'Role node updated.');
    }

    public function setMember(RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);

        $level->update(['kind' => 'member', 'group_id' => null, 'role_id' => null]);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Node converted to a member node.');
    }

    public function destroyLevel(RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);

        $level->delete();

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return back()->with('success', 'Node removed.');
    }

    public function updateTag(Request $request, RoleHierarchyLevel $level)
    {
        $user = Auth::user();
        abort_unless($this->canManage($user), 403);
        $this->assertOwnsHierarchy($level, $user);

        $tag = trim((string) ($request->validate([
            'tag' => 'nullable|string|max:40',
        ])['tag'] ?? ''));

        if ($tag === '') {
            return response()->json(['message' => 'Tag cannot be empty.'], 422);
        }

        $all = RoleHierarchyLevel::where('role_hierarchy_id', $level->role_hierarchy_id)
            ->where('level', $level->level)
            ->orderBy('id')
            ->get(['id', 'tag']);

        foreach ($all as $i => $sibling) {
            if ((int) $sibling->id === (int) $level->id) {
                continue;
            }

            $effective = $sibling->tag !== null && trim($sibling->tag) !== ''
                ? trim($sibling->tag)
                : '('.($i + 1).')';

            if ($effective === $tag) {
                return response()->json(['message' => 'Another node at this level already uses "'.$tag.'".'], 422);
            }
        }

        $level->update(['tag' => $tag]);

        WorkspaceSync::bump($user->tenant_id, ['hierarchies']);

        return response()->json(['ok' => true, 'tag' => $tag]);
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

    private function assertOwnsHierarchy(RoleHierarchyLevel $level, User $user): void
    {
        abort_unless(
            (int) $level->role_hierarchy_id > 0
            && RoleHierarchy::where('id', $level->role_hierarchy_id)->where('tenant_id', $user->tenant_id)->exists(),
            404
        );
    }

    private function canManage(User $user): bool
    {
        return $this->tenantPermissionService->hasPermission($user, Permissions::TENANT_ROLES_VIEW)
            || $this->roleHierarchyService->isTenantFounder($user);
    }
}
