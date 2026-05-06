<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use App\Services\ChatablePermissionService;
use App\Support\Permissions;

class MessagePolicy
{
    public function __construct(
        private readonly ChatablePermissionService $chatablePermissionService
    ) {
    }

    public function viewAny(User $viewer, mixed $chatable): bool
    {
        return $this->chatablePermissionService->hasPermission($chatable, $viewer, Permissions::MESSAGES_VIEW);
    }

    public function create(User $creator, mixed $chatable): bool
    {
        return $this->chatablePermissionService->hasPermission($chatable, $creator, Permissions::MESSAGES_CREATE);
    }

    public function update(User $editor, Message $message): bool
    {
        if ($message->user_id !== $editor->id) {
            return false;
        }

        return $this->chatablePermissionService->hasPermission($message->chatable, $editor, Permissions::MESSAGES_UPDATE_OWN);
    }

    public function delete(User $deleter, Message $message): bool
    {
        if ($message->user_id === $deleter->id) {
            return $this->chatablePermissionService->hasPermission($message->chatable, $deleter, Permissions::MESSAGES_DELETE_OWN);
        }

        return $this->chatablePermissionService->hasPermission($message->chatable, $deleter, Permissions::MESSAGES_DELETE_ANY);
    }
}