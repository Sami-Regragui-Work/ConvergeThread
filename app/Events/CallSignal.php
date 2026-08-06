<?php

namespace App\Events;

use App\Support\ChatChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $chatType,
        public int $chatId,
        public array $payload,
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
        return 'call.signal';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
