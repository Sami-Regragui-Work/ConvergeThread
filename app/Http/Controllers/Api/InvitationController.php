<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptInvitationRequest;
use App\Http\Requests\Api\CreateOwnerInvitationRequest;
use App\Http\Requests\Api\CreateTenantInvitationRequest;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService
    ) {
    }

    public function createOwnerInvitation(CreateOwnerInvitationRequest $request): JsonResponse
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

    public function createTenantInvitation(CreateTenantInvitationRequest $request): JsonResponse
    {
        $cridentials = $request->validated();

        $invitedBy = $request->user();

        $invitation = $this->invitationService->createTenantInvitation(
            $cridentials['email'],
            $invitedBy,
            $cridentials['tenant_id'],
            $cridentials['group_id'],
            $cridentials['tenant_role_id']
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

    public function accept(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        $cridentials = $request->validated();

        $result = $this->invitationService->acceptInvitation(
            $token,
            $cridentials['password'],
            $cridentials['display_name']
        );

        return response()->json($result, 201);
    }
}
