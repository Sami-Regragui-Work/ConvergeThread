<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDuoRequest;
use App\Models\Duo;
use App\Models\Group;
use App\Models\User;
use App\Services\DuoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DuoController extends Controller
{
    public function __construct(private readonly DuoService $duoService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Group $group)
    {
        $user = Auth::user();
        Gate::authorize('viewAny', [Duo::class, $group]);

        $duos = $this->duoService->getUserDuos($group, $user);

        return view('duos.index', compact('duos', 'group'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDuoRequest $request, Group $group)
    {
        $cridentials = $request->validated();
        Gate::authorize('create', [Duo::class, $group]);

        $user1 = User::where('tenant_id', $group->tenant_id)->findOrFail($cridentials['user1_id']);
        $user2 = User::where('tenant_id', $group->tenant_id)->findOrFail($cridentials['user2_id']);

        $this->duoService->create($group, $user1, $user2, $cridentials['name']);

        return redirect()
            ->route('groups.duos.index', $group)
            ->with('success', 'Duo created successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group, Duo $duo)
    {
        Gate::authorize('delete', [Duo::class, $group]);

        $this->duoService->delete($duo);

        return redirect()
            ->route('groups.duos.index', $group)
            ->with('success', 'Duo deleted successfully.');
    }
}
