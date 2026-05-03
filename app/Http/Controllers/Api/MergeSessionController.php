<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMergeSessionRequest;
use App\Models\Group;
use App\Models\MergeSession;
use App\Services\MergeSessionService;
use Illuminate\Http\JsonResponse;

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
        $sessions = $this->mergeService->getActive();
        return response()->json($sessions->load('groups'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMergeSessionRequest $request): JsonResponse
    {
        $cridentials = $request->validated();
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
        return response()->json($mergeSession->load('groups'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MergeSession $mergeSession): JsonResponse
    {
        $session = $this->mergeService->end($mergeSession);
        return response()->json($session);
    }
}
