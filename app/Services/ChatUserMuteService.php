<?php

namespace App\Services;

use App\Models\ChatUserMute;
use App\Models\User;

class ChatUserMuteService
{
    /**
     * Flag map for a chat, keyed by muted_user_id:
     * [userId => ['notifications' => bool, 'calls' => bool, 'shrink' => bool]]
     *
     * @return array<int, array{notifications: bool, calls: bool, shrink: bool}>
     */
    public function flagsForChat(User $user, string $chatableType, int $chatableId): array
    {
        return ChatUserMute::query()
            ->where('user_id', $user->id)
            ->where('chatable_type', $chatableType)
            ->where('chatable_id', $chatableId)
            ->get()
            ->mapWithKeys(fn (ChatUserMute $mute) => [
                (int) $mute->muted_user_id => [
                    'notifications' => (bool) $mute->mute_notifications,
                    'calls' => (bool) $mute->mute_calls,
                    'shrink' => (bool) $mute->shrink_messages,
                ],
            ])
            ->all();
    }

    /**
     * Whether $user has muted $mutedUserId for the given chat + flag.
     */
    public function isMuted(User $user, int $mutedUserId, string $chatableType, int $chatableId, string $flag): bool
    {
        $column = match ($flag) {
            'notifications' => 'mute_notifications',
            'calls' => 'mute_calls',
            'shrink' => 'shrink_messages',
            default => null,
        };

        if (! $column) {
            return false;
        }

        return ChatUserMute::query()
            ->where('user_id', $user->id)
            ->where('muted_user_id', $mutedUserId)
            ->where('chatable_type', $chatableType)
            ->where('chatable_id', $chatableId)
            ->where($column, true)
            ->exists();
    }

    /**
     * Upsert a mute row and return the resulting flags.
     *
     * @return array{notifications: bool, calls: bool, shrink: bool}
     */
    public function save(
        User $user,
        int $mutedUserId,
        string $chatableType,
        int $chatableId,
        bool $muteNotifications = false,
        bool $muteCalls = false,
        bool $shrinkMessages = false,
    ): array {
        $query = [
            'user_id' => $user->id,
            'chatable_type' => $chatableType,
            'chatable_id' => $chatableId,
            'muted_user_id' => $mutedUserId,
        ];

        if (! $muteNotifications && ! $muteCalls && ! $shrinkMessages) {
            ChatUserMute::query()->where($query)->delete();
        } else {
            ChatUserMute::updateOrCreate($query, [
                'mute_notifications' => $muteNotifications,
                'mute_calls' => $muteCalls,
                'shrink_messages' => $shrinkMessages,
            ]);
        }

        return [
            'notifications' => $muteNotifications,
            'calls' => $muteCalls,
            'shrink' => $shrinkMessages,
        ];
    }
}
