<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreGroupRequest;
use App\Http\Requests\Api\UpdateGroupRequest;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function __construct(private GroupService $groupService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', Group::class);

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
        Gate::authorize('create', Group::class);

        $user = $request->user();

        $group = $this->groupService->create(
            $cridentials['name'],
            $user
        );

        return response()->json($group->load(['creator', 'tenant']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group): JsonResponse
    {
        Gate::authorize('view', $group);
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
        Gate::authorize('update', $group);

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
    public function destroy(Request $request, Group $group): JsonResponse
    {
        $deleter = $request->user();
        Gate::authorize('delete', $group);

        $this->groupService->delete($group, $deleter);

        return response()->json(null, 204);
    }
}
