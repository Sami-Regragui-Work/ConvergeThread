<?php

namespace App\Notifications;

use App\Models\Message;
use App\Support\MessageEncryption;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChatMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
        public readonly string $chatType,
        public readonly string $chatLabel,
        public readonly int $stackCount = 1,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing('user');

        $preview = ($this->message->is_encrypted || MessageEncryption::isEncrypted($this->message->content))
            ? 'Encrypted message'
            : str($this->message->content)->limit(80)->toString();

        return [
            'type' => 'chat_message',
            'message_id' => $this->message->id,
            'chat_type' => $this->chatType,
            'chatable_id' => $this->message->chatable_id,
            'chat_label' => $this->chatLabel,
            'stack_count' => $this->stackCount,
            'author_name' => $this->message->user->display_name ?? $this->message->user->username,
            'preview' => $preview,
            'url' => route('messages.index', [$this->chatType, $this->message->chatable_id]),
        ];
    }
}
