<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptInvitationRequest;
use App\Http\Requests\Api\CreateAdminInvitationRequest;
use App\Http\Requests\Api\CreateMemberInvitationRequest;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService
    ) {
    }

    public function createAdminInvitation(CreateAdminInvitationRequest $request): JsonResponse
    {
        $cridentials = $request->validated();

        $owner = $request->user();

        $invitation = $this->invitationService->createAdminInvitation(
            $cridentials['email'],
            $owner
        );

        return response()->json([
            'invitation' => $invitation->load('invitedBy'),
            'accept_url' => config('app.url') . '/api/invitations/' . $invitation->token . '/accept',
        ], 201);
    }

    public function createMemberInvitation(CreateMemberInvitationRequest $request): JsonResponse
    {
        $cridentials = $request->validated();

        $invitedBy = $request->user();

        $tenant = Tenant::findOrFail($cridentials['tenant_id']);

        $group = isset($cridentials['group_id'])
            ? Group::where('tenant_id', $tenant->id)->findOrFail($cridentials['group_id'])
            : null;

        $tenantRole = isset($cridentials['tenant_role_id'])
            ? TenantRole::where('tenant_id', $tenant->id)->findOrFail($cridentials['tenant_role_id'])
            : null;

        $invitation = $this->invitationService->createMemberInvitation(
            $cridentials['email'],
            $invitedBy,
            $tenant,
            $group,
            $tenantRole
        );

        return response()->json([
            'invitation' => $invitation->load(['tenant', 'group', 'tenantRole', 'invitedBy']),
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
            $cridentials['display_name'] ?? null
        );

        $result['user']->load(['tenant', 'tenantRole']);

        if ((int) $result['invitation']->tenant_id === 0) {
            $result['invitation']->load('invitedBy');
        } else {
            $result['invitation']->load(['invitedBy', 'tenant', 'tenantRole', 'group']);
        }

        return response()->json($result, 201);
    }
}
