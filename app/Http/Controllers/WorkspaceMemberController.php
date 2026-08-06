<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\RoleChangedNotification;
use App\Services\RoleHierarchyService;
use App\Services\TenantPermissionService;
use App\Support\WorkspaceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WorkspaceMemberController extends Controller
{
    public function __construct(
        private readonly TenantPermissionService $tenantPermissionService,
        private readonly RoleHierarchyService $roleHierarchyService,
    ) {
    }

    public function index()
    {
        $user = Auth::user();
        abort_unless($this->tenantPermissionService->canViewWorkspaceMembers($user), 403);

        $canManage = $this->tenantPermissionService->canManageWorkspaceMembers($user);

        $members = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('banned_by_id')
            ->with('tenantRole')
            ->orderBy('display_name')
            ->get();

        $assignableByMember = $canManage
            ? $members->mapWithKeys(function (User $member) use ($user) {
                return [
                    $member->id => $this->roleHierarchyService->assignableRolesFor($user, $member),
                ];
            })
            : collect();

        $pendingInvitations = $canManage
            ? Invitation::query()
                ->where('tenant_id', $user->tenant_id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->with(['group', 'tenantRole'])
                ->latest()
                ->get()
            : collect();

        return view('workspace.members.index', compact(
            'members',
            'assignableByMember',
            'pendingInvitations',
            'canManage',
        ));
    }

    public function updateRole(Request $request, User $member)
    {
        $user = Auth::user();
        abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($user), 403);
        abort_unless((int) $member->tenant_id === (int) $user->tenant_id, 404);

        $request->validate([
            'tenant_role_id' => 'required|exists:tenant_roles,id',
        ]);

        $tenantRole = TenantRole::query()
            ->forTenant($user->tenant_id)
            ->findOrFail($request->input('tenant_role_id'));

        try {
            $this->roleHierarchyService->assertCanAssignRole($user, $member, $tenantRole);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['tenant_role_id' => $e->getMessage()]);
        }

        $member->update(['tenant_role_id' => $tenantRole->id]);
        $member->notify(new RoleChangedNotification($tenantRole->name, 'workspace'));
        WorkspaceSync::bump($user->tenant_id, ['users', 'members']);

        return back()->with('success', 'Role updated.');
    }
}
