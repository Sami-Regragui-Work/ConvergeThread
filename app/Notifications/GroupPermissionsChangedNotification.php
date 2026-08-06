<?php

namespace App\Notifications;

use App\Models\Group;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GroupPermissionsChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Group $group,
        public readonly string $summary,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'group_permissions',
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'summary' => $this->summary,
            'url' => route('groups.show', $this->group),
        ];
    }
}
