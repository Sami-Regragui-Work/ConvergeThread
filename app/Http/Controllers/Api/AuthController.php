<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'username' => 'required|unique:users',
            'display_name' => 'nullable|string|max:100',
            'tenant_slug' => 'required',
        ]);

        $tenant = Tenant::where('name', $request->tenant_slug)->firstOrFail();

        try {
            $response = $this->authService->register(
                $request->email,
                $request->password,
                $request->username,
                $request->display_name ?? null,
                $tenant
            );
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'email' => [$e->getMessage()],
            ]);
        }

        return response()->json($response, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'tenant_slug' => 'required',
        ]);

        try {
            $response = $this->authService->login(
                $request->email,
                $request->password
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
