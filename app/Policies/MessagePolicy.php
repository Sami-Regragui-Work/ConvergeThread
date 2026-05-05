<?php

namespace App\Policies;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatablePermissionService;

class MessagePolicy
{
    public function __construct(
        private readonly ChatablePermissionService $chatablePermissionService
    ) {
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $viewer, Message $message): bool
    {
        return $this->chatablePermissionService->hasPermission(
            $message->chatable,
            $viewer,
            'messages.view'
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $creator, Group|Duo|MergeSession $chatable): bool
    {
        return $this->chatablePermissionService->hasPermission(
            $chatable,
            $creator,
            'messages.create'
        );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $editor, Message $message): bool
    {
        return $message->user_id === $editor->id
            && $this->view($editor, $message);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Message $message): bool
    {
        return $message->user_id === $deleter->id
            && $this->view($deleter, $message);
    }

    public function thread(User $viewer, Message $message): bool
    {
        return $this->view($viewer, $message);
    }
}
