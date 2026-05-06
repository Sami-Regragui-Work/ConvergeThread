<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\CreateAdminInvitationRequest;
use App\Http\Requests\CreateMemberInvitationRequest;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService
    ) {
    }

    public function createAdminInvitation(CreateAdminInvitationRequest $request)
    {
        $cridentials = $request->validated();
        Gate::authorize('createAdmin', Invitation::class);

        $owner = Auth::user();

        $invitation = $this->invitationService->createAdminInvitation(
            $cridentials['email'],
            $owner
        );

        return redirect()
            ->back()
            ->with('success', 'Admin invitation created successfully.')
            ->with('accept_url', route('invitations.accept', $invitation->token));
    }

    public function createMemberInvitation(CreateMemberInvitationRequest $request)
    {
        $cridentials = $request->validated();
        Gate::authorize('createMember', Invitation::class);

        $invitedBy = Auth::user();

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

        return redirect()
            ->back()
            ->with('success', 'Member invitation created successfully.')
            ->with('accept_url', route('invitations.accept', $invitation->token));
    }

    public function show(string $token)
    {
        $invitation = $this->invitationService->findOpen($token);

        return view('invitations.show', [
            'invitation' => $invitation,
            'acceptUrl' => route('invitations.accept', $token),
        ]);
    }

    public function showAccept(string $token)
    {
        $invitation = $this->invitationService->findOpen($token);

        return view('invitations.accept', compact('invitation'));
    }

    public function accept(AcceptInvitationRequest $request, string $token)
    {
        $cridentials = $request->validated();

        $result = $this->invitationService->acceptInvitation(
            $token,
            $cridentials['password'],
            $cridentials['display_name'] ?? null
        );

        $result['user']->load(['tenant', 'tenantRole']);

        if ($result['user']->isOwner()) {
            $result['invitation']->load('invitedBy');
        } else {
            $result['invitation']->load(['invitedBy', 'tenant', 'tenantRole', 'group']);
        }

        return redirect()
            ->route('auth.login')
            ->with('success', 'Invitation accepted successfully. You can now log in.');
    }
}
