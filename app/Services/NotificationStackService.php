<?php

namespace App\Services;

use App\Models\ChatMute;
use App\Models\Message;
use App\Models\ThreadMute;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use Illuminate\Support\Facades\DB;

class NotificationStackService
{
    public function notifyChatMessage(
        Message $message,
        string $chatType,
        string $chatLabel,
        User $recipient,
    ): void {
        if ($this->isChatMuted($recipient, $message->chatable_type, $message->chatable_id)) {
            return;
        }

        if ($message->parent_id && $this->isThreadMuted($recipient, $message->parent_id)) {
            return;
        }

        $existing = $recipient->notifications()
            ->where('type', ChatMessageNotification::class)
            ->whereNull('read_at')
            ->where('data->chatable_id', $message->chatable_id)
            ->where('data->chat_type', $chatType)
            ->first();

        if ($existing) {
            $data = $existing->data;
            $data['stack_count'] = ($data['stack_count'] ?? 1) + 1;
            $data['message_id'] = $message->id;
            $data['preview'] = str($message->content)->limit(80)->toString();
            $data['author_name'] = $message->user->display_name ?? $message->user->username;
            $existing->update(['data' => $data, 'created_at' => now()]);

            return;
        }

        $recipient->notify(new ChatMessageNotification($message, $chatType, $chatLabel));
    }

    public function isChatMuted(User $user, string $chatableType, int $chatableId): bool
    {
        return ChatMute::query()
            ->where('user_id', $user->id)
            ->where('chatable_type', $chatableType)
            ->where('chatable_id', $chatableId)
            ->exists();
    }

    public function isThreadMuted(User $user, int $threadRootId): bool
    {
        return ThreadMute::query()
            ->where('user_id', $user->id)
            ->where('message_id', $threadRootId)
            ->exists();
    }

    public function toggleChatMute(User $user, string $chatableType, int $chatableId): bool
    {
        $existing = ChatMute::query()
            ->where('user_id', $user->id)
            ->where('chatable_type', $chatableType)
            ->where('chatable_id', $chatableId)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        ChatMute::create([
            'user_id' => $user->id,
            'chatable_type' => $chatableType,
            'chatable_id' => $chatableId,
        ]);

        return true;
    }

    public function toggleThreadMute(User $user, int $threadRootId): bool
    {
        $existing = ThreadMute::query()
            ->where('user_id', $user->id)
            ->where('message_id', $threadRootId)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        ThreadMute::create([
            'user_id' => $user->id,
            'message_id' => $threadRootId,
        ]);

        return true;
    }
}
