<?php

namespace App\Events;

use App\Models\Message;
use App\Support\ChatChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing('user');
    }

    public function broadcastOn(): array
    {
        $type = $this->message->chatable_type;
        $id = $this->message->chatable_id;

        return [
            new PrivateChannel(ChatChannel::name($type, $id)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->toChatPayload(),
        ];
    }
}
