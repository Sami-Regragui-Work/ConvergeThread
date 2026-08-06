<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IncomingCallNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $callerName,
        public readonly string $chatType,
        public readonly int $chatableId,
        public readonly string $callId,
        public readonly string $callType,
        public readonly string $chatLabel,
        public readonly string $url,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $kind = $this->callType === 'video' ? 'Video' : 'Voice';

        return [
            'type' => 'incoming_call',
            'call_id' => $this->callId,
            'call_type' => $this->callType,
            'chat_type' => $this->chatType,
            'chatable_id' => $this->chatableId,
            'chat_label' => $this->chatLabel,
            'author_name' => $this->callerName,
            'preview' => $kind.' call in '.$this->chatLabel,
            'url' => $this->url,
        ];
    }
}
