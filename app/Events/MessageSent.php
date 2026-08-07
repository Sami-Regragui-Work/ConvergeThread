<?php

namespace App\Events;

use App\Models\Message;
use App\Services\MentionService;
use App\Support\ChatChannel;
use App\Support\MessageEncryption;
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
        $this->message->loadMissing('user.tenantRole', 'attachments', 'deletedBy', 'replies');
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
        $payload = $this->message->toChatPayload();

        if (
            ($payload['content'] ?? null)
            && ! ($payload['is_encrypted'] ?? false)
            && ! MessageEncryption::isEncrypted($payload['content'])
        ) {
            $payload['content_html'] = app(MentionService::class)->renderContentHtml(
                $payload['content'],
                [],
                [],
                [],
                (bool) ($payload['is_markdown'] ?? false),
            );
        } else {
            $payload['content_html'] = null;
        }

        return [
            'message' => $payload,
        ];
    }
}
