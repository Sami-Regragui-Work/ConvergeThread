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
use App\Support\Flash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService
    ) {
    }

    public function createAdminInvitation(CreateAdminInvitationRequest $request)
    {
        $credentials = $request->validated();
        Gate::authorize('createAdmin', Invitation::class);

        $owner = Auth::user();

        $invitation = $this->invitationService->createAdminInvitation(
            $credentials['email'],
            $owner
        );

        return Flash::back(
            'Admin invitation created. Share the link below with the new tenant admin.',
            [[
                'label' => 'Admin invitation link',
                'url' => route('invitations.accept', $invitation->token),
            ]],
        );
    }

    public function createMemberInvitation(CreateMemberInvitationRequest $request)
    {
        $credentials = $request->validated();
        Gate::authorize('createMember', Invitation::class);

        $invitedBy = Auth::user();

        $tenant = Tenant::findOrFail($credentials['tenant_id']);

        $group = isset($credentials['group_id'])
            ? Group::where('tenant_id', $tenant->id)->findOrFail($credentials['group_id'])
            : null;

        $tenantRole = isset($credentials['tenant_role_id'])
            ? TenantRole::where('tenant_id', $tenant->id)->findOrFail($credentials['tenant_role_id'])
            : null;

        $invitation = $this->invitationService->createMemberInvitation(
            $credentials['email'],
            $invitedBy,
            $tenant,
            $group,
            $tenantRole
        );

        $label = $group
            ? 'Group invitation link'
            : 'Workspace invitation link';

        return Flash::back(
            'Member invitation created. Share the link below with the invitee.',
            [[
                'label' => $label,
                'url' => route('invitations.accept', $invitation->token),
            ]],
        );
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
        $credentials = $request->validated();

        $isAdminInvite = (bool) ($credentials['is_admin_invite'] ?? false);

        if ($isAdminInvite) {
            $this->invitationService->acceptAdminInvitation(
                $token,
                $credentials['password'],
                $credentials['tenant_name'],
                $credentials['display_name'] ?? null,
            );
        } else {
            $result = $this->invitationService->acceptInvitation(
                $token,
                $credentials['password'],
                $credentials['display_name'] ?? null
            );

            $result['user']->load(['tenant', 'tenantRole']);
        }

        return redirect()
            ->route('auth.login')
            ->with('success', 'Invitation accepted successfully. You can now log in.');
    }
}