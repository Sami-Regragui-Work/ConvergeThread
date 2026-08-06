<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChatKeyShare extends Model
{
    protected $fillable = [
        'chatable_id',
        'chatable_type',
        'user_id',
        'wrapped_key',
        'ephemeral_public_key',
    ];

    public function chatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
