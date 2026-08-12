<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\CallLogParticipant;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CallLogService
{
    /**
     * Create the call log + participants for a new invite. The caller is marked
     * as joined; every invitee is recorded as a pending callee (later joined /
     * declined / missed).
     */
    public function start(
        Group|Duo|MergeSession $chatable,
        string $chatType,
        User $caller,
        string $callId,
        string $callType,
        string $chatLabel,
    ): CallLog {
        return DB::transaction(function () use ($chatable, $chatType, $caller, $callId, $callType, $chatLabel) {
            $log = CallLog::create([
                'chat_type' => $chatType,
                'chatable_id' => (int) $chatable->id,
                'chat_label' => $chatLabel,
                'call_id' => $callId,
                'caller_user_id' => (int) $caller->id,
                'call_type' => $callType,
                'status' => 'ongoing',
                'started_at' => now(),
            ]);

            $this->addParticipant($log, $caller, 'caller', 'joined', now());

            app(ChatParticipantService::class)
                ->participants($chatable)
                ->reject(fn (User $user) => (int) $user->id === (int) $caller->id)
                ->each(fn (User $user) => $this->addParticipant($log, $user, 'callee', 'pending', null));

            return $log;
        });
    }

    private function addParticipant(CallLog $log, User $user, string $role, string $status, ?\DateTimeInterface $joinedAt): CallLogParticipant
    {
        return CallLogParticipant::create([
            'call_log_id' => $log->id,
            'user_id' => (int) $user->id,
            'role' => $role,
            'status' => $status,
            'joined_at' => $joinedAt,
            'timeline' => $status === 'joined'
                ? [['type' => $role === 'caller' ? 'started' : 'joined', 'at' => now()->toIso8601String()]]
                : null,
        ]);
    }

    public function markJoined(string $callId, int $userId): void
    {
        $log = CallLog::where('call_id', $callId)->first();
        $participant = $log?->participants()->where('user_id', $userId)->first();

        if (! $participant) {
            return;
        }

        if ($participant->status === 'joined') {
            return;
        }

        $wasDeclined = in_array($participant->status, ['declined', 'missed'], true);

        $participant->update([
            'status' => 'joined',
            'joined_at' => now(),
            'left_at' => null,
            'timeline' => $participant->addTimeline($wasDeclined ? 'rejoined' : 'joined'),
        ]);
    }

    public function markLeft(string $callId, int $userId): void
    {
        $log = CallLog::where('call_id', $callId)->first();

        if (! $log) {
            return;
        }

        $participant = $log->participants()->where('user_id', $userId)->first();
        $now = now();

        if ($participant && $participant->status === 'joined') {
            $joined = $participant->joined_at ?? $log->started_at ?? $now;
            $participant->update([
                'status' => 'left',
                'left_at' => $now,
                'duration' => $participant->duration + max(0, (int) round($joined->diffInSeconds($now))),
                'timeline' => $participant->addTimeline('left'),
            ]);
        }

        $this->maybeFinalize($log);
    }

    public function markDeclined(string $callId, int $userId): void
    {
        $log = CallLog::where('call_id', $callId)->first();
        $participant = $log?->participants()->where('user_id', $userId)->first();

        if ($participant && $participant->status === 'pending') {
            $participant->update([
                'status' => 'declined',
                'timeline' => $participant->addTimeline('declined'),
            ]);
        }
    }

    public function touch(string $callId): void
    {
        CallLog::where('call_id', $callId)
            ->where('status', 'ongoing')
            ->update(['updated_at' => now()]);
    }

    public function finalize(string $callId, ?string $status = null): void
    {
        $log = CallLog::where('call_id', $callId)->first();

        if (! $log || ! $log->isOngoing()) {
            return;
        }

        $now = now();

        foreach ($log->participants()->where('status', 'joined')->get() as $participant) {
            $joined = $participant->joined_at ?? $log->started_at ?? $now;
            $participant->update([
                'status' => 'left',
                'left_at' => $now,
                'duration' => $participant->duration + max(0, (int) round($joined->diffInSeconds($now))),
                'timeline' => $participant->addTimeline('left'),
            ]);
        }

        $log->participants()->where('status', 'pending')->update(['status' => 'missed']);

        $started = $log->started_at ?? $now;

        $log->update([
            'status' => $status ?? $this->deriveStatus($log),
            'ended_at' => $now,
            'total_duration' => max(0, (int) round($started->diffInSeconds($now))),
        ]);
    }

    /**
     * Close out any call that has been silent for a while (heartbeat stopped,
     * no leave signal — e.g. the browser was closed).
     */
    public function finalizeStale(int $olderThanMinutes = 10): void
    {
        CallLog::where('status', 'ongoing')
            ->where('updated_at', '<', now()->subMinutes($olderThanMinutes))
            ->pluck('call_id')
            ->each(fn (string $callId) => $this->finalize($callId));
    }

    private function maybeFinalize(CallLog $log): void
    {
        if (! $log->isOngoing()) {
            return;
        }

        $activeJoined = $log->participants()->where('status', 'joined')->exists();

        if (! $activeJoined) {
            $this->finalize($log->call_id);
        }
    }

    private function deriveStatus(CallLog $log): string
    {
        if ($log->participants()->where('role', 'callee')->whereIn('status', ['joined', 'left'])->exists()) {
            return 'completed';
        }

        if ($log->participants()->where('role', 'callee')->where('status', 'declined')->exists()) {
            return 'declined';
        }

        return 'missed';
    }
}
