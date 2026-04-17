<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateOwnerInvitationRequest;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService
    ) {
    }

    public function createOwner(CreateOwnerInvitationRequest $request): JsonResponse
    {
        $cridentials = $request->validated();

        $owner = $request->user();

        $invitation = $this->invitationService->createOwnerInvitation(
            $cridentials['email'],
            $owner
        );

        return response()->json([
            'invitation' => $invitation->load('invitedBy'),
            'accept_url' => config('app.url') . '/api/invitations/' . $invitation->token . '/accept',
        ], 201);
    }

    public function createTenant(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'tenant_id' => 'required|exists:tenants,id',
            'group_id' => 'nullable|exists:groups,id',
            'tenant_role_id' => 'nullable|exists:tenant_roles,id',
        ]);

        $invitedBy = $request->user();

        $invitation = $this->invitationService->createTenantInvitation(
            $request->email,
            $invitedBy,
            $request->tenant_id,
            $request->group_id,
            $request->tenant_role_id
        );

        return response()->json([
            'invitation' => $invitation->load('tenant', 'group', 'tenantRole', 'invitedBy'),
            'accept_url' => config('app.url') . '/api/invitations/' . $invitation->token . '/accept',
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $invitation = Invitation::where('token', $token)
            ->with(['tenant', 'group', 'tenantRole', 'invitedBy'])
            ->firstOrFail();

        if ($invitation->accepted_at) {
            throw ValidationException::withMessages([
                'token' => 'Invitation already accepted.',
            ]);
        }

        if ($invitation->expires_at < now()) {
            throw ValidationException::withMessages([
                'token' => 'Invitation expired.',
            ]);
        }

        return response()->json([
            'invitation' => $invitation,
            'accept_url' => config('app.url') . '/api/invitations/' . $token . '/accept',
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'display_name' => 'nullable|string|max:100',
        ]);

        $result = $this->invitationService->acceptInvitation(
            $token,
            $request->password,
            $request->display_name
        );

        return response()->json($result, 201);
    }
}
