<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'chatable_id',
        'chatable_type',
        'user_id',
        'content',
        'is_file',
        'file_path',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'is_file' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function chatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    public function toChatPayload(): array
    {
        $this->loadMissing('user');

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->display_name ?? $this->user->email,
            'user_initial' => strtoupper(substr($this->user->display_name ?? $this->user->email, 0, 1)),
            'content' => $this->content,
            'is_file' => $this->is_file,
            'file_url' => $this->is_file && $this->file_path
                ? asset('storage/' . $this->file_path)
                : null,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at?->diffForHumans(),
            'reply_count' => $this->relationLoaded('replies') ? $this->replies->count() : 0,
        ];
    }
}
