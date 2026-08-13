<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChatUserMute extends Model
{
    protected $fillable = [
        'user_id',
        'chatable_type',
        'chatable_id',
        'muted_user_id',
        'mute_notifications',
        'mute_calls',
        'shrink_messages',
    ];

    protected $casts = [
        'mute_notifications' => 'boolean',
        'mute_calls' => 'boolean',
        'shrink_messages' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mutedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_user_id');
    }

    public function chatable(): MorphTo
    {
        return $this->morphTo();
    }
}
