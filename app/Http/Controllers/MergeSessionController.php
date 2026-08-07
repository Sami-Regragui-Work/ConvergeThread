<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMergeSessionRequest;
use App\Models\Group;
use App\Models\MergeSession;
use App\Services\MergeSessionService;
use App\Support\Flash;
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

        $sessions = $this->mergeService->getActiveForTenant(Auth::user()->tenant_id);
        $groups = Group::where('tenant_id', Auth::user()->tenant_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('merge-sessions.index', compact('sessions', 'groups'));
    }

    /**
     * Show the form for creating a newly created resource.
     */
    public function create()
    {
        Gate::authorize('create', MergeSession::class);

        $groups = Group::where('tenant_id', Auth::user()->tenant_id)
            ->orderBy('name')
            ->get();

        return view('merge-sessions.create', compact('groups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMergeSessionRequest $request)
    {
        $credentials = $request->validated();
        Gate::authorize('create', MergeSession::class);

        $user = Auth::user();

        $group1 = Group::where('tenant_id', $user->tenant_id)
            ->findOrFail($credentials['group1_id']);

        $group2 = Group::where('tenant_id', $user->tenant_id)
            ->findOrFail($credentials['group2_id']);

        $session = $this->mergeService->start($group1, $group2);

        $chatUrl = route('messages.index', ['merge', $session->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'session' => [
                    'id' => $session->id,
                    'name' => $session->name,
                    'url' => route('merge-sessions.show', $session),
                    'chat_url' => $chatUrl,
                ],
            ]);
        }

        return Flash::to(
            'merge-sessions.show',
            'Merge session created. Share the chat link with members of both groups.',
            [[
                'label' => 'Merged chat link',
                'url' => $chatUrl,
            ]],
            $session,
        );
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
