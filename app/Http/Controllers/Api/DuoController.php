<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Duo;
use App\Models\Group;
use App\Models\User;
use App\Services\DuoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DuoController extends Controller
{
    public function __construct(private readonly DuoService $duoService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Group $group): JsonResponse
    {
        $user = request()->user();
        $duos = $this->duoService->getUserDuos($group, $user);
        return response()->json($duos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDuoRequest $request, Group $group): JsonResponse
    {
        $credentials = $request->validated();
        $user1 = User::where('tenant_id', $group->tenant_id)->findOrFail($credentials['user1_id']);
        $user2 = User::where('tenant_id', $group->tenant_id)->findOrFail($credentials['user2_id']);

        $duo = $this->duoService->create($group, $user1, $user2, $credentials['name']);

        return response()->json($duo->load(['user1', 'user2']), 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Duo $duo): JsonResponse
    {
        $this->duoService->delete($duo);
        return response()->json(null, 204);
    }
}
