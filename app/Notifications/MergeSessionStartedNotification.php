<?php

namespace App\Notifications;

use App\Models\MergeSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MergeSessionStartedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly MergeSession $session,
        public readonly string $groupName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'merge_session',
            'merge_session_id' => $this->session->id,
            'group_name' => $this->groupName,
            'url' => route('messages.index', ['merge', $this->session->id]),
        ];
    }
}
