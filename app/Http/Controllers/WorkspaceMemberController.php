<?php

namespace App\Http\Controllers;

use App\Models\Duo;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\RegistrationRequest;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\RoleChangedNotification;
use App\Services\RoleHierarchyService;
use App\Services\TenantPermissionService;
use App\Support\SortsLists;
use App\Support\WorkspaceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WorkspaceMemberController extends Controller
{
    use SortsLists;

    public function __construct(
        private readonly TenantPermissionService $tenantPermissionService,
        private readonly RoleHierarchyService $roleHierarchyService,
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($this->tenantPermissionService->canViewWorkspaceMembers($user), 403);

        $canManage = $this->tenantPermissionService->canManageWorkspaceMembers($user);

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['display_name', 'email', 'created_at'],
            'display_name',
            'asc',
        );

        $members = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('banned_by_id')
            ->with('tenantRole')
            ->orderBy($sort, $dir)
            ->get();

        $assignableByMember = $canManage
            ? $members->mapWithKeys(function (User $member) use ($user) {
                return [
                    $member->id => $this->roleHierarchyService->assignableRolesFor($user, $member),
                ];
            })
            : collect();

        $removableByMember = $canManage
            ? $members->mapWithKeys(function (User $member) use ($user) {
                return [
                    $member->id => $member->id !== $user->id
                        && $this->roleHierarchyService->canManageUser($user, $member),
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

        $pendingRegistrations = $canManage
            ? RegistrationRequest::query()
                ->where('tenant_id', $user->tenant_id)
                ->where('status', 'pending')
                ->latest()
                ->get()
            : collect();

        return view('workspace.members.index', compact(
            'members',
            'assignableByMember',
            'removableByMember',
            'pendingInvitations',
            'pendingRegistrations',
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

    public function destroy(User $member)
    {
        /** @var User $user */
        $user = Auth::user();

        abort_unless($this->tenantPermissionService->canManageWorkspaceMembers($user), 403);
        abort_unless((int) $member->tenant_id === (int) $user->tenant_id, 404);
        abort_if((int) $member->id === (int) $user->id, 403, 'You cannot remove yourself from the workspace.');
        abort_unless($this->roleHierarchyService->canManageUser($user, $member), 403, 'You cannot remove this member (equal or higher role).');

        $groupIds = Group::where('tenant_id', $user->tenant_id)->pluck('id');

        GroupMember::whereIn('group_id', $groupIds)
            ->where('user_id', $member->id)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);

        Duo::whereIn('group_id', $groupIds)
            ->where(fn ($query) => $query->where('user1_id', $member->id)->orWhere('user2_id', $member->id))
            ->delete();

        $member->update(['tenant_role_id' => null]);

        WorkspaceSync::bump($user->tenant_id, ['users', 'members', 'groups']);

        return back()->with('success', $member->displayLabel().' has been removed from the workspace.');
    }
}
