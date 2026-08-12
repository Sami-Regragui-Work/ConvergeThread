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
use App\Support\SortsLists;
use App\Support\WorkspaceSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    use SortsLists;

    public function __construct(
        private readonly InvitationService $invitationService,
    ) {
    }

    public function manage(Request $request)
    {
        Gate::authorize('createMember', Invitation::class);
        $user = Auth::user();

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['email', 'created_at', 'expires_at'],
            'created_at',
            'desc',
        );

        $invitations = Invitation::query()
            ->when(!$user->isOwner(), fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->with(['tenant', 'group', 'tenantRole', 'invitedBy'])
            ->orderBy($sort, $dir)
            ->get()
            ->groupBy(fn (Invitation $invitation) => $invitation->status());

        return view('invitations.index', compact('invitations'));
    }

    public function revoke(Invitation $invitation)
    {
        Gate::authorize('createMember', Invitation::class);
        $user = Auth::user();
        abort_unless(
            $invitation->invited_by_id === $user->id
            || (int) $invitation->tenant_id === (int) $user->tenant_id,
            403,
        );
        abort_if($invitation->accepted_at !== null, 404);

        $invitation->update(['expires_at' => now(), 'revoked_at' => now()]);

        WorkspaceSync::bump($user->tenant_id, ['invitations']);

        return back()->with('success', 'Invitation revoked.');
    }

    public function clearClosed()
    {
        Gate::authorize('createMember', Invitation::class);
        $user = Auth::user();

        $query = Invitation::query()
            ->when(!$user->isOwner(), fn ($query) => $query->where('tenant_id', $user->tenant_id))
            ->where(fn ($query) => $query
                ->whereNotNull('accepted_at')
                ->orWhereNotNull('revoked_at')
                ->orWhere('expires_at', '<', now()));

        $count = $query->count();
        $query->delete();

        if ($count > 0) {
            WorkspaceSync::bump($user->tenant_id, ['invitations']);
        }

        return back()->with('success', "Cleared {$count} closed invitation" . ($count === 1 ? '' : 's') . '.');
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
            ? TenantRole::query()->forTenant($tenant->id)->findOrFail($credentials['tenant_role_id'])
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