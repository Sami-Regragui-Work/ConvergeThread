<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;
use App\Notifications\IncomingCallNotification;
use Illuminate\Support\Facades\Cache;

class CallSessionService
{
    private const TTL_SECONDS = 180;

    public function cacheKey(string $chatType, int $chatId): string
    {
        return 'active_call:'.$chatType.':'.$chatId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function rememberInvite(string $chatType, int $chatId, array $payload): void
    {
        Cache::put($this->cacheKey($chatType, $chatId), [
            'call_id' => $payload['call_id'],
            'call_type' => $payload['call_type'],
            'from_user_id' => $payload['from_user_id'],
            'from_user_name' => $payload['from_user_name'],
            'started_at' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    public function touch(string $chatType, int $chatId): void
    {
        $key = $this->cacheKey($chatType, $chatId);
        $existing = Cache::get($key);
        if (is_array($existing)) {
            Cache::put($key, $existing, self::TTL_SECONDS);
        }
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
        Cache::forget($key);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function active(string $chatType, int $chatId): ?array
    {
        $value = Cache::get($this->cacheKey($chatType, $chatId));

        return is_array($value) ? $value : null;
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
            });
    }
}
