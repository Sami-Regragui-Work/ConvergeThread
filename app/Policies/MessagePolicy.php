<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\GroupPermissionService;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function __construct(
        private readonly GroupPermissionService $groupPermissionService
    ) {
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $viewer, Message $message): bool
    {
        $group = $this->resolveGroup($message);

        if (!$group) {
            return false;
        }

        return $this->groupPermissionService->hasPermission($group, $viewer, 'messages.view');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $editor, Message $message): bool
    {
        if ($message->user_id === $editor->id) {
            return true;
        }

        $group = $this->resolveGroup($message);

        if (!$group) {
            return false;
        }

        return $this->groupPermissionService->hasPermission($group, $editor, 'messages.update_any');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $deleter, Message $message): bool
    {
        if ($message->user_id === $deleter->id) {
            return true;
        }

        $group = $this->resolveGroup($message);

        if (!$group) {
            return false;
        }

        return $this->groupPermissionService->hasPermission($group, $deleter, 'messages.delete_any');
    }

    private function resolveGroup(Message $message): ?Group
    {
        $chatable = $message->chatable;

        if ($chatable instanceof Group) {
            return $chatable;
        }

        if (method_exists($chatable, 'group') && $chatable->group) {
            return $chatable->group;
        }

        if (method_exists($chatable, 'groups') && $chatable->groups()->count() > 0) {
            return $chatable->groups()->first();
        }

        return null;
    }
}
