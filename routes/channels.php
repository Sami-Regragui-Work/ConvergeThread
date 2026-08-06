<?php

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

Broadcast::channel('chat.{chatType}.{chatId}', function ($user, string $chatType, int $chatId) {
    $chatable = match ($chatType) {
        'group' => Group::where('tenant_id', $user->tenant_id)->find($chatId),
        'duo' => Duo::whereHas('group', fn ($q) => $q->where('tenant_id', $user->tenant_id))->find($chatId),
        'merge' => MergeSession::query()
            ->forTenant($user->tenant_id)
            ->find($chatId),
        default => null,
    };

    if (!$chatable) {
        return false;
    }

    return Gate::forUser($user)->allows('viewAny', [Message::class, $chatable]);
});

Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('workspace.{tenantId}', function ($user, int $tenantId) {
    return (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('owner', function ($user) {
    return $user->isOwner();
});
