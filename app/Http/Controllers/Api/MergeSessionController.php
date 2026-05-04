<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMergeSessionRequest;
use App\Models\Group;
use App\Models\MergeSession;
use App\Services\MergeSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MergeSessionController extends Controller
{
    public function __construct(private readonly MergeSessionService $mergeService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', MergeSession::class);

        $sessions = $this->mergeService->getActive();
        return response()->json($sessions->load('groups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMergeSessionRequest $request): JsonResponse
    {
        $cridentials = $request->validated();
        Gate::authorize('create', MergeSession::class);

        $user = $request->user();

        $group1 = Group::where('tenant_id', $user->tenant_id)
            ->findOrFail($cridentials['group1_id']);

        $group2 = Group::where('tenant_id', $user->tenant_id)
            ->findOrFail($cridentials['group2_id']);

        $session = $this->mergeService->start($group1, $group2);

        return response()->json($session->load('groups'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MergeSession $mergeSession): JsonResponse
    {
        Gate::authorize('view', $mergeSession);

        return response()->json($mergeSession->load('groups'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MergeSession $mergeSession): JsonResponse
    {
        Gate::authorize('delete', $mergeSession);
        
        $session = $this->mergeService->end($mergeSession);
        return response()->json($session);
    }
}
