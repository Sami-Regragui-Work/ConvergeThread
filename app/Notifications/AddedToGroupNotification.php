<?php

namespace App\Notifications;

use App\Models\Group;
use App\Models\MergeSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AddedToGroupNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Group $group,
        public readonly string $addedByName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'added_to_group',
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'added_by' => $this->addedByName,
            'url' => route('groups.show', $this->group),
        ];
    }
}
