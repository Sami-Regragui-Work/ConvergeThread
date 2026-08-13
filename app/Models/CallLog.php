<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_type',
        'chatable_id',
        'chat_label',
        'call_id',
        'caller_user_id',
        'call_type',
        'status',
        'started_at',
        'ended_at',
        'total_duration',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_duration' => 'integer',
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_user_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallLogParticipant::class);
    }

    public function participantFor(int $userId): ?CallLogParticipant
    {
        return $this->participants->first(fn (CallLogParticipant $p) => (int) $p->user_id === $userId);
    }

    public function chatUrl(): string
    {
        return route('messages.index', [$this->chat_type, $this->chatable_id]);
    }

    public function isOngoing(): bool
    {
        return $this->status === 'ongoing';
    }

    /**
     * Per-viewer status used in the calls list. The call-level status describes
     * the call as a whole; this translates it into what makes sense for the
     * user looking at the row: the caller who hung up sees "Left" (not
     * "Completed"), the caller who hung up before anyone answered sees
     * "Canceled", and a callee who ended the call by leaving last sees
     * "Completed" instead of "Left".
     */
    public function statusFor(int $userId): string
    {
        if ($this->isOngoing()) {
            return 'ongoing';
        }

        $participant = $this->participantFor($userId);

        if (! $participant) {
            return $this->status;
        }

        if (in_array($participant->status, ['pending', 'declined', 'missed'], true)) {
            return $participant->status;
        }

        if ((int) $this->caller_user_id === $userId) {
            return $this->status === 'completed' ? 'left' : $this->status;
        }

        if ($participant->status === 'joined') {
            return 'joined';
        }

        $othersLeft = $this->participants
            ->reject(fn (CallLogParticipant $p) => (int) $p->user_id === $userId)
            ->contains(fn (CallLogParticipant $p) => $p->status === 'left');

        return $othersLeft ? 'completed' : 'left';
    }
}
