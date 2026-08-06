<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MentionedInChatNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
        public readonly string $chatType,
        public readonly string $mentionType,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing('user');

        return [
            'message_id' => $this->message->id,
            'chat_type' => $this->chatType,
            'chatable_id' => $this->message->chatable_id,
            'mention_type' => $this->mentionType,
            'author_name' => $this->message->user->display_name ?? $this->message->user->username,
            'preview' => str($this->message->content)->limit(120)->toString(),
        ];
    }
}
