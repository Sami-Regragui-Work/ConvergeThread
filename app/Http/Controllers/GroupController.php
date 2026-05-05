<?php


namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Services\GroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GroupController extends Controller
{
    public function __construct(private GroupService $groupService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        Gate::authorize('viewAny', Group::class);

        $groups = Group::where('tenant_id', $user->tenant_id)
            ->withCount(['activeMembers'])
            ->with('creator:id,display_name')
            ->get();

        return view('groups.index', compact('groups'));
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
        $cridentials = $request->validated();
        Gate::authorize('create', Group::class);

        $user = Auth::user();

        $group = $this->groupService->create(
            $cridentials['name'],
            $user
        );

        return redirect()
            ->route('groups.show', $group)
            ->with('success', 'Group created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group)
    {
        Gate::authorize('view', $group);

        $group->load([
            'creator:id,display_name',
            'activeMembers:id,display_name,username',
            'tenant:id,name'
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
        $cridentials = $request->validated();
        Gate::authorize('update', $group);

        $user = Auth::user();

        $group = $this->groupService->updateName(
            $group,
            $user,
            $cridentials['name']
        );

        return redirect()
            ->route('groups.show', $group)
            ->with('success', 'Group updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Group $group)
    {
        $deleter = Auth::user();
        Gate::authorize('delete', $group);

        $this->groupService->delete($group, $deleter);

        return redirect()
            ->route('groups.index')
            ->with('success', 'Group deleted successfully.');
    }
}
