<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RoleChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $roleName,
        public readonly ?string $context = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'role_changed',
            'role_name' => $this->roleName,
            'context' => $this->context,
            'url' => route('groups.index'),
        ];
    }
}
