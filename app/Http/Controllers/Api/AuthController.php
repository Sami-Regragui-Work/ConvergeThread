<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Tenant;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $cridentials = $request->validated();

        try {
            $response = $this->authService->register(
                $cridentials['email'],
                $cridentials['password'],
                $cridentials['display_name'] ?? null,
                $cridentials['tenant_slug']
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 422);
        }

        return response()->json($response, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $cridentials = $request->validated();


        try {
            $response = $this->authService->login(
                $cridentials['email'],
                $cridentials['password']
            );
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 401);
        }

        return response()->json($response);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json(['message' => 'Logged out']);
    }

    public function refresh(): JsonResponse
    {
        try {
            $response = $this->authService->refresh();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], $e->getCode() ?: 401);
        }

        return response()->json($response);
    }
}
