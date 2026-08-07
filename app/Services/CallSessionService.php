<?php

namespace App\Services;

use App\Events\UserIncomingCall;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;
use App\Notifications\IncomingCallNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CallSessionService
{
    private const TTL_SECONDS = 90;

    public function cacheKey(string $chatType, int $chatId): string
    {
        return 'active_call:'.$chatType.':'.$chatId;
    }

    public function userCallKey(int $userId): string
    {
        return 'user_in_call:'.$userId;
    }

    /**
     * @return array{chat_type:string,chat_id:int,call_id:string}|null
     */
    public function userActiveCall(int $userId): ?array
    {
        $value = Cache::get($this->userCallKey($userId));

        return is_array($value) ? $value : null;
    }

    public function assertUserCanJoin(int $userId, string $chatType, int $chatId, string $callId): void
    {
        $existing = $this->userActiveCall($userId);
        if (! $existing) {
            return;
        }

        $sameCall = ($existing['chat_type'] ?? null) === $chatType
            && (int) ($existing['chat_id'] ?? 0) === $chatId
            && ($existing['call_id'] ?? null) === $callId;

        if ($sameCall) {
            return;
        }

        throw ValidationException::withMessages([
            'call' => ['You are already in another call. Leave it before joining a new one.'],
        ]);
    }

    public function bindUserToCall(int $userId, string $chatType, int $chatId, string $callId): void
    {
        Cache::put($this->userCallKey($userId), [
            'chat_type' => $chatType,
            'chat_id' => $chatId,
            'call_id' => $callId,
        ], self::TTL_SECONDS);
    }

    public function unbindUserFromCall(int $userId, ?string $callId = null): void
    {
        if ($callId !== null) {
            $existing = $this->userActiveCall($userId);
            if ($existing && ($existing['call_id'] ?? null) !== $callId) {
                return;
            }
        }
        Cache::forget($this->userCallKey($userId));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function rememberInvite(string $chatType, int $chatId, array $payload): void
    {
        $fromId = (int) $payload['from_user_id'];

        Cache::put($this->cacheKey($chatType, $chatId), [
            'call_id' => $payload['call_id'],
            'call_type' => $payload['call_type'],
            'media_mode' => $payload['media_mode'] ?? 'mesh',
            'from_user_id' => $fromId,
            'from_user_name' => $payload['from_user_name'],
            'started_at' => now()->toIso8601String(),
            'participants' => [
                (string) $fromId => now()->timestamp,
            ],
        ], self::TTL_SECONDS);

        $this->bindUserToCall($fromId, $chatType, $chatId, (string) $payload['call_id']);
    }

    public function touch(string $chatType, int $chatId): void
    {
        $key = $this->cacheKey($chatType, $chatId);
        $existing = Cache::get($key);
        if (is_array($existing)) {
            Cache::put($key, $existing, self::TTL_SECONDS);
        }
    }

    public function markParticipant(string $chatType, int $chatId, int $userId, ?string $callId = null): void
    {
        $key = $this->cacheKey($chatType, $chatId);
        $existing = Cache::get($key);
        if (! is_array($existing)) {
            return;
        }
        if ($callId !== null && ($existing['call_id'] ?? null) !== $callId) {
            return;
        }

        $participants = is_array($existing['participants'] ?? null) ? $existing['participants'] : [];
        $participants[(string) $userId] = now()->timestamp;
        $existing['participants'] = $participants;

        Cache::put($key, $existing, self::TTL_SECONDS);

        if ($callId) {
            $this->bindUserToCall($userId, $chatType, $chatId, $callId);
        }
    }

    /**
     * Remove a participant. Clears the session only when nobody remains.
     *
     * @return bool True when the whole session ended
     */
    public function removeParticipant(string $chatType, int $chatId, int $userId, ?string $callId = null): bool
    {
        $key = $this->cacheKey($chatType, $chatId);
        $existing = Cache::get($key);
        if (! is_array($existing)) {
            $this->unbindUserFromCall($userId, $callId);

            return true;
        }
        if ($callId !== null && ($existing['call_id'] ?? null) !== $callId) {
            return false;
        }

        $participants = is_array($existing['participants'] ?? null) ? $existing['participants'] : [];
        unset($participants[(string) $userId]);
        $existing['participants'] = $participants;

        $this->unbindUserFromCall($userId, $callId);

        if ($participants === []) {
            Cache::forget($key);

            return true;
        }

        Cache::put($key, $existing, self::TTL_SECONDS);

        return false;
    }

    public function clear(string $chatType, int $chatId, ?string $callId = null): void
    {
        $key = $this->cacheKey($chatType, $chatId);
        $existing = Cache::get($key);
        if (! is_array($existing)) {
            return;
        }
        if ($callId !== null && ($existing['call_id'] ?? null) !== $callId) {
            return;
        }

        $participants = is_array($existing['participants'] ?? null) ? $existing['participants'] : [];
        foreach (array_keys($participants) as $uid) {
            $this->unbindUserFromCall((int) $uid, $callId ?? ($existing['call_id'] ?? null));
        }

        Cache::forget($key);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function active(string $chatType, int $chatId): ?array
    {
        $value = Cache::get($this->cacheKey($chatType, $chatId));
        if (! is_array($value)) {
            return null;
        }

        $participants = is_array($value['participants'] ?? null) ? $value['participants'] : [];
        $value['participant_ids'] = array_map('intval', array_keys($participants));
        $value['participant_count'] = count($participants);

        return $value;
    }

    public function notifyParticipants(
        Group|Duo|MergeSession $chatable,
        string $chatType,
        User $caller,
        string $callId,
        string $callType,
    ): void {
        $label = $chatable->name ?? ($chatType.' #'.$chatable->id);
        $url = route('messages.index', [$chatType, $chatable->id]).'?join_call=1';

        app(ChatParticipantService::class)
            ->participants($chatable)
            ->filter(fn (User $user) => (int) $user->id !== (int) $caller->id)
            ->each(function (User $user) use ($caller, $chatType, $chatable, $callId, $callType, $label, $url) {
                $user->notify(new IncomingCallNotification(
                    callerName: $caller->displayLabel(),
                    chatType: $chatType,
                    chatableId: (int) $chatable->id,
                    callId: $callId,
                    callType: $callType,
                    chatLabel: $label,
                    url: $url,
                ));

                UserIncomingCall::dispatch((int) $user->id, [
                    'call_id' => $callId,
                    'call_type' => $callType,
                    'chat_type' => $chatType,
                    'chat_id' => (int) $chatable->id,
                    'chat_label' => $label,
                    'from_user_id' => (int) $caller->id,
                    'from_user_name' => $caller->displayLabel(),
                    'url' => $url,
                ]);
            });
    }
}
