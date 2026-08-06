<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddGroupMemberRequest;
use App\Http\Requests\AssignGroupMemberRoleRequest;
use App\Http\Requests\AssignTenantRoleRequest;
use App\Http\Requests\RemoveGroupMemberRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupRoleOverride;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupMemberService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GroupMemberController extends Controller
{
    public function __construct(
        private readonly GroupMemberService $groupMemberService
    ) {}

    public function index(Group $group)
    {
        Gate::authorize('viewAny', [GroupMember::class, $group]);

        $members = $this->groupMemberService->getActive($group)->load('user.tenantRole');
        $memberUserIds = $members->pluck('user_id');

        $availableUsers = User::where('tenant_id', $group->tenant_id)
            ->whereNotIn('id', $memberUserIds)
            ->whereNull('banned_by_id')
            ->orderBy('display_name')
            ->get();

        $roleOverrides = $group->groupRoleOverrides()->with('tenantRole')->get();
        /** @var User $user */
        $user = Auth::user();
        $tenantRoles = TenantRole::assignableForInviter($user);

        return view('groups.members.index', compact(
            'members',
            'group',
            'availableUsers',
            'roleOverrides',
            'tenantRoles',
        ));
    }

    public function store(AddGroupMemberRequest $request, Group $group)
    {
        $credentials = $request->validated();
        Gate::authorize('create', [GroupMember::class, $group]);

        $user = User::where('tenant_id', $group->tenant_id)
            ->findOrFail($credentials['user_id']);

        $this->groupMemberService->add($group, $user);

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', 'Member added successfully.');
    }

    public function destroy(RemoveGroupMemberRequest $request, Group $group)
    {
        $credentials = $request->validated();
        Gate::authorize('delete', [GroupMember::class, $group]);

        $member = User::where('tenant_id', $group->tenant_id)
            ->findOrFail($credentials['user_id']);

        $this->groupMemberService->remove($group, $member);

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', 'Member removed successfully.');
    }

    public function assignRole(
        AssignGroupMemberRoleRequest $request,
        Group $group
    ) {
        $credentials = $request->validated();
        Gate::authorize('assignRole', [GroupMember::class, $group]);

        $member = User::where('tenant_id', $group->tenant_id)
            ->findOrFail($credentials['user_id']);

        $roleOverride = isset($credentials['group_role_override_id'])
            ? GroupRoleOverride::where('group_id', $group->id)
            ->findOrFail($credentials['group_role_override_id'])
            : null;

        $this->groupMemberService->assignRole(
            $group,
            $member,
            $roleOverride
        );

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', 'Member role updated successfully.');
    }

    public function assignTenantRole(
        AssignTenantRoleRequest $request,
        Group $group
    ) {
        $credentials = $request->validated();
        Gate::authorize('assignTenantRole', [GroupMember::class, $group]);

        $member = User::where('tenant_id', $group->tenant_id)
            ->findOrFail($credentials['user_id']);

        $tenantRole = TenantRole::query()
            ->forTenant($group->tenant_id)
            ->findOrFail($credentials['tenant_role_id']);

        $this->groupMemberService->assignTenantRole($group, $member, $tenantRole);

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', 'Workspace role updated successfully.');
    }
}
