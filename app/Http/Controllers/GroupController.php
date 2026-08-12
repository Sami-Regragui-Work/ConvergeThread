<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use App\Support\SortsLists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    use SortsLists;

    public function __construct(private GroupService $groupService) {}

    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        /** @var User */
        $user = Auth::user();
        Gate::authorize('viewAny', Group::class);

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['name', 'created_at', 'active_members_count'],
            'created_at',
            'asc',
        );

        return view('groups.index', $this->groupService->getIndexDataForUser($user, $sort, $dir));
    }

    /**
     * Show the form for creating a newly created resource.
     */
    public function create()
    {
        Gate::authorize('create', Group::class);

        return view('groups.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGroupRequest $request)
    {
        $credentials = $request->validated();
        Gate::authorize('create', Group::class);

        $user = Auth::user();

        $group = $this->groupService->create(
            $credentials['name'],
            $user
        );

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'url' => route('groups.show', $group),
                ],
            ]);
        }

        return redirect()
            ->route('groups.index', $group)
            ->with('success', 'Group created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group)
    {
        Gate::authorize('view', $group);

        $group->load([
            'creator:id,display_name,username,email',
            'activeMembers.tenantRole',
            'tenant:id,slug',
            'duos:id,group_id,name,user1_id,user2_id',
        ]);

        return view('groups.show', compact('group'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Group $group)
    {
        Gate::authorize('update', $group);

        return view('groups.edit', compact('group'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGroupRequest $request, Group $group)
    {
        $credentials = $request->validated();
        Gate::authorize('update', $group);

        $group = $this->groupService->update($group, $credentials);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'accent_color' => $group->accent_color,
                ],
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Group updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group)
    {
        Gate::authorize('delete', $group);

        $this->groupService->delete($group);

        return redirect()
            ->route('groups.index')
            ->with('success', 'Group deleted successfully.');
    }
}
