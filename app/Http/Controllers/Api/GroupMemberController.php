<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddGroupMemberRequest;
use App\Http\Requests\Api\AssignGroupMemberRoleRequest;
use App\Http\Requests\Api\RemoveGroupMemberRequest;
use App\Models\Group;
use App\Models\GroupRoleOverride;
use App\Models\User;
use App\Services\GroupMemberService;
use Illuminate\Http\JsonResponse;

class GroupMemberController extends Controller
{
    public function __construct(
        private readonly GroupMemberService $groupMemberService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Group $group): JsonResponse
    {
        $members = $this->groupMemberService->getActive($group);
        return response()->json($members);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AddGroupMemberRequest $request, Group $group): JsonResponse
    {
        $cridentials = $request->validated();

        $member = $this->groupMemberService->add(
            $group,
            User::find($cridentials['user_id'])
        );

        return response()->json($member, 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RemoveGroupMemberRequest $request, Group $group): JsonResponse
    {
        $cridentials = $request->validated();

        $this->groupMemberService->remove(
            $group,
            User::find($cridentials['user_id'])
        );

        return response()->json(null, 204);
    }

    public function assignRole(
        AssignGroupMemberRoleRequest $request,
        Group $group
    ): JsonResponse {
        $cridentials = $request->validated();

        $roleOverride = GroupRoleOverride::findOrFail($cridentials['group_role_override_id']);

        $member = $this->groupMemberService->assignRole(
            $group,
            User::find($cridentials['user_id']),
            $roleOverride
        );

        return response()->json($member);
    }
}
