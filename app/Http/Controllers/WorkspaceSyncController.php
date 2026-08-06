<?php

namespace App\Http\Controllers;

use App\Models\Duo;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\MergeSession;
use App\Models\RoleHierarchy;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class WorkspaceSyncController extends Controller
{
    public function poll()
    {
        $user = Auth::user();

        if ($user->isOwner()) {
            return response()->json([
                'unread_notifications' => $user->unreadNotifications()->count(),
                'users' => optional(User::query()->max('updated_at'))?->toIso8601String(),
                'tenants' => optional(Tenant::query()->max('updated_at'))?->toIso8601String(),
                'groups' => optional(Group::query()->max('updated_at'))?->toIso8601String(),
                'members' => optional(GroupMember::query()->max('updated_at'))?->toIso8601String(),
                'duos' => optional(Duo::query()->max('updated_at'))?->toIso8601String(),
                'roles' => optional(TenantRole::query()->max('updated_at'))?->toIso8601String(),
                'invitations' => optional(Invitation::query()->max('updated_at'))?->toIso8601String(),
                'merges' => $this->mergeStamp(null),
                'hierarchies' => optional(RoleHierarchy::query()->max('updated_at'))?->toIso8601String(),
                'groups_updated_at' => optional(Group::query()->max('updated_at'))?->toIso8601String(),
                'users_updated_at' => optional(User::query()->max('updated_at'))?->toIso8601String(),
                'server_time' => now()->toIso8601String(),
            ]);
        }

        $tenantId = (int) $user->tenant_id;
        $groupIds = Group::query()->where('tenant_id', $tenantId)->pluck('id');

        return response()->json([
            'unread_notifications' => $user->unreadNotifications()->count(),
            'groups' => optional(Group::query()->where('tenant_id', $tenantId)->max('updated_at'))?->toIso8601String(),
            'members' => optional(GroupMember::query()->whereIn('group_id', $groupIds)->max('updated_at'))?->toIso8601String(),
            'users' => optional(User::query()->where('tenant_id', $tenantId)->max('updated_at'))?->toIso8601String(),
            'roles' => optional(TenantRole::query()->where('tenant_id', $tenantId)->orWhere('is_system', true)->max('updated_at'))?->toIso8601String(),
            'invitations' => optional(Invitation::query()->where('tenant_id', $tenantId)->max('updated_at'))?->toIso8601String(),
            'merges' => $this->mergeStamp($tenantId),
            'hierarchies' => optional(RoleHierarchy::query()->where('tenant_id', $tenantId)->max('updated_at'))?->toIso8601String(),
            'groups_updated_at' => optional(Group::query()->where('tenant_id', $tenantId)->max('updated_at'))?->toIso8601String(),
            'users_updated_at' => optional(User::query()->where('tenant_id', $tenantId)->max('updated_at'))?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function mergeStamp(?int $tenantId): ?string
    {
        $query = MergeSession::query();

        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }

        $stamp = $query->selectRaw('MAX(COALESCE(ended_at, started_at)) as stamp')->value('stamp');

        return $stamp ? (string) $stamp : null;
    }
}
