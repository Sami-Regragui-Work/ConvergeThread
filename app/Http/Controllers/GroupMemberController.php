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
use App\Services\RoleHierarchyService;
use App\Support\SortsLists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GroupMemberController extends Controller
{
    use SortsLists;

    public function __construct(
        private readonly GroupMemberService $groupMemberService,
        private readonly RoleHierarchyService $roleHierarchyService,
    ) {}

    public function index(Request $request, Group $group)
    {
        Gate::authorize('viewAny', [GroupMember::class, $group]);

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['display_name', 'joined_at'],
            'display_name',
            'asc',
        );

        $members = $this->groupMemberService->getActive($group)->load('user.tenantRole');

        $members = $members->sortBy(
            fn (GroupMember $member) => match ($sort) {
                'display_name' => strtolower($member->user->displayLabel()),
                default => $member->created_at?->getTimestamp() ?? 0,
            },
            SORT_REGULAR,
            $dir === 'desc',
        )->values();

        $memberUserIds = $members->pluck('user_id');

        $availableUsers = User::where('tenant_id', $group->tenant_id)
            ->whereNotIn('id', $memberUserIds)
            ->whereNull('banned_by_id')
            ->orderBy('display_name')
            ->get();

        $roleOverrides = $group->groupRoleOverrides()->with('tenantRole')->get();
        /** @var User $user */
        $user = Auth::user();

        $assignableByMember = $members->mapWithKeys(function (GroupMember $gm) use ($user) {
            return [
                $gm->user_id => $this->roleHierarchyService->assignableRolesFor($user, $gm->user),
            ];
        });

        $inviteRoles = TenantRole::assignableForInviter($user);

        return view('groups.members.index', compact(
            'members',
            'group',
            'availableUsers',
            'roleOverrides',
            'assignableByMember',
            'inviteRoles',
        ));
    }

    public function store(AddGroupMemberRequest $request, Group $group)
    {
        $credentials = $request->validated();
        Gate::authorize('create', [GroupMember::class, $group]);

        $users = User::where('tenant_id', $group->tenant_id)
            ->whereIn('id', $credentials['user_ids'])
            ->get();

        foreach ($users as $user) {
            $this->groupMemberService->add($group, $user, Auth::user());
        }

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', count($users).' member'.(count($users) === 1 ? '' : 's').' added successfully.');
    }

    public function leave(Group $group)
    {
        $user = Auth::user();

        abort_if((int) $group->creator_id === (int) $user->id, 403, 'The group creator cannot leave. You can delete the group instead.');

        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->first();

        abort_unless($membership !== null, 404, 'You are not a member of this group.');

        $this->groupMemberService->remove($group, $user);

        return redirect()
            ->route('groups.index')
            ->with('success', 'You left the group.');
    }

    public function destroy(RemoveGroupMemberRequest $request, Group $group)
    {
        $credentials = $request->validated();

        $member = GroupMember::where('group_id', $group->id)
            ->where('user_id', $credentials['user_id'])
            ->whereNull('left_at')
            ->first();

        abort_unless($member !== null, 404, 'Member is not active in this group.');

        Gate::authorize('delete', [$member, $group]);

        $this->groupMemberService->remove($group, $member->user);

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

        try {
            $this->roleHierarchyService->assertCanAssignRole(Auth::user(), $member, $tenantRole);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['tenant_role_id' => $e->getMessage()]);
        }

        $this->groupMemberService->assignTenantRole($group, $member, $tenantRole);

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', 'Workspace role updated successfully.');
    }
}
