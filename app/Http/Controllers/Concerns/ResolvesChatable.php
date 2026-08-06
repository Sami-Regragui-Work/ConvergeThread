<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;

trait ResolvesChatable
{
    protected function resolveChatable(User $user, string $chatType, int $chatId): Group|Duo|MergeSession
    {
        return match ($chatType) {
            'group' => Group::where('tenant_id', $user->tenant_id)->findOrFail($chatId),
            'duo' => Duo::whereHas('group', fn ($q) => $q->where('tenant_id', $user->tenant_id))
                ->findOrFail($chatId),
            'merge' => MergeSession::query()
                ->forTenant($user->tenant_id)
                ->findOrFail($chatId),
            default => abort(404, 'Invalid chat type'),
        };
    }
}
