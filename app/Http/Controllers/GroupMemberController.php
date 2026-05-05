<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddGroupMemberRequest;
use App\Http\Requests\AssignGroupMemberRoleRequest;
use App\Http\Requests\RemoveGroupMemberRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupRoleOverride;
use App\Models\User;
use App\Services\GroupMemberService;
use Illuminate\Support\Facades\Gate;

class GroupMemberController extends Controller
{
    public function __construct(
        private readonly GroupMemberService $groupMemberService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Group $group)
    {
        Gate::authorize('viewAny', [GroupMember::class, $group]);

        $members = $this->groupMemberService->getActive($group);

        return view('group_members.index', compact('members', 'group'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AddGroupMemberRequest $request, Group $group)
    {
        $cridentials = $request->validated();
        Gate::authorize('create', [GroupMember::class, $group]);

        $user = User::where('tenant_id', $group->tenant_id)
            ->findOrFail($cridentials['user_id']);

        $this->groupMemberService->add($group, $user);

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', 'Member added successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RemoveGroupMemberRequest $request, Group $group)
    {
        $cridentials = $request->validated();
        Gate::authorize('delete', [GroupMember::class, $group]);

        $member = User::where('tenant_id', $group->tenant_id)
            ->findOrFail($cridentials['user_id']);

        $this->groupMemberService->remove($group, $member);

        return redirect()
            ->route('groups.members.index', $group)
            ->with('success', 'Member removed successfully.');
    }

    public function assignRole(
        AssignGroupMemberRoleRequest $request,
        Group $group
    ) {
        $cridentials = $request->validated();
        Gate::authorize('assignRole', [GroupMember::class, $group]);

        $member = User::where('tenant_id', $group->tenant_id)
            ->findOrFail($cridentials['user_id']);

        $roleOverride = isset($cridentials['group_role_override_id'])
            ? GroupRoleOverride::where('group_id', $group->id)
                ->findOrFail($cridentials['group_role_override_id'])
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
}
