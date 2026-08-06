<?php

namespace App\Events;

use App\Support\ChatChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatKeyNeeded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $chatType,
        public int $chatId,
        public int $userId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(ChatChannel::name($this->chatType, $this->chatId)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.key.needed';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
        ];
    }
}
