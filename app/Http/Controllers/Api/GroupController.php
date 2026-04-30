<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGroupRequest;
use App\Http\Requests\Api\UpdateGroupRequest;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use Symfony\Component\HttpFoundation\JsonResponse;

class GroupController extends Controller
{
    public function __construct(private GroupService $groupService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $user = request()->user();

        $groups = Group::where('tenant_id', $user->tenant_id)
            ->withCount(['activeMembers'])
            ->with('creator:id,display_name')
            ->get();

        return response()->json($groups);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupRequest $request): JsonResponse
    {
        $cridentials = $request->validated();

        $user = $request->user();

        $group = $this->groupService->create(
            $cridentials['name'],
            $user
        );

        return response()->json($group->load('creator', 'tenant'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group): JsonResponse
    {
        return response()->json($group->load([
            'creator:id,display_name',
            'activeMembers:id,display_name,username',
            'tenant:id,name'
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupRequest $request, Group $group): JsonResponse
    {
        $cridentials = $request->validated();

        $user = $request->user();

        $group = $this->groupService->updateName(
            $group,
            $user,
            $cridentials['name']
        );

        return response()->json($group->load('creator'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group, User $user): JsonResponse
    {
        $this->groupService->delete($group, $user);

        return response()->json(null, 204);
    }
}
