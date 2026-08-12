<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLogParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_log_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'left_at',
        'duration',
        'timeline',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'duration' => 'integer',
            'timeline' => 'array',
        ];
    }

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addTimeline(string $type): array
    {
        $timeline = $this->timeline ?? [];
        $timeline[] = ['type' => $type, 'at' => now()->toIso8601String()];

        return $timeline;
    }
}
