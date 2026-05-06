<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMergeSessionRequest;
use App\Models\Group;
use App\Models\MergeSession;
use App\Services\MergeSessionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MergeSessionController extends Controller
{
    public function __construct(private readonly MergeSessionService $mergeService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', MergeSession::class);

        $sessions = $this->mergeService->getActive()->load('groups');

        return view('merge-sessions.index', compact('sessions'));
    }

    /**
     * Show the form for creating a newly created resource.
     */
    public function create()
    {
        Gate::authorize('create', MergeSession::class);

        return view('merge-sessions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMergeSessionRequest $request)
    {
        $cridentials = $request->validated();
        Gate::authorize('create', MergeSession::class);

        $user = Auth::user();

        $group1 = Group::where('tenant_id', $user->tenant_id)
            ->findOrFail($cridentials['group1_id']);

        $group2 = Group::where('tenant_id', $user->tenant_id)
            ->findOrFail($cridentials['group2_id']);

        $session = $this->mergeService->start($group1, $group2);

        return redirect()
            ->route('merge-sessions.show', $session)
            ->with('success', 'Merge session created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MergeSession $mergeSession)
    {
        Gate::authorize('view', $mergeSession);

        $mergeSession->load('groups');

        return view('merge-sessions.show', compact('mergeSession'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MergeSession $mergeSession)
    {
        Gate::authorize('delete', $mergeSession);

        $this->mergeService->end($mergeSession);

        return redirect()
            ->route('merge-sessions.index')
            ->with('success', 'Merge session ended successfully.');
    }
}
