<?php

namespace App\Models;

use App\Models\MessageAttachment;
use App\Models\MessageMention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    protected $fillable = [
        'chatable_id',
        'chatable_type',
        'user_id',
        'content',
        'is_encrypted',
        'is_markdown',
        'is_file',
        'file_path',
        'parent_id',
        'edited_at',
        'deleted_at',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_file' => 'boolean',
            'is_encrypted' => 'boolean',
            'is_markdown' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
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

    public function mentions(): HasMany
    {
        return $this->hasMany(MessageMention::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class)->orderBy('sort');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function hides(): HasMany
    {
        return $this->hasMany(MessageHide::class);
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function toChatPayload(?int $viewerId = null): array
    {
        $this->loadMissing(['user.tenantRole', 'attachments', 'deletedBy']);

        if ($this->isDeleted()) {
            $deletedBy = $this->deletedBy;
            $deletedByName = $deletedBy
                ? ($deletedBy->display_name ?? $deletedBy->email)
                : 'Someone';

            return [
                'id' => $this->id,
                'user_id' => $this->user_id,
                'user_name' => $this->user->display_name ?? $this->user->email,
                'user_role_color' => $this->user->tenantRole?->color,
                'user_initial' => strtoupper(substr($this->user->display_name ?? $this->user->email, 0, 1)),
                'content' => null,
                'content_html' => null,
                'is_encrypted' => false,
                'is_markdown' => false,
                'is_file' => false,
                'file_url' => null,
                'file_preview_url' => null,
                'attachments' => [],
                'parent_id' => $this->parent_id,
                'created_at' => $this->created_at?->diffForHumans(),
                'created_at_iso' => $this->created_at?->toIso8601String(),
                'updated_at' => null,
                'is_edited' => false,
                'is_deleted' => true,
                'deleted_by_id' => $this->deleted_by_id,
                'deleted_by_name' => $deletedByName,
                'deleted_at' => $this->deleted_at?->diffForHumans(),
                'reply_count' => $this->relationLoaded('replies') ? $this->replies->count() : 0,
                'mentions_you' => false,
            ];
        }

        $legacyFileUrl = $this->is_file && $this->file_path
            ? route('messages.attachment', $this)
            : null;

        $legacyName = $this->file_path ? basename($this->file_path) : 'file';
        $legacyExt = strtoupper(pathinfo($legacyName, PATHINFO_EXTENSION) ?: 'FILE');
        $legacyIsImage = $this->is_file && $this->file_path
            && preg_match('/\.(jpe?g|png|gif|webp)$/i', $this->file_path);
        $legacyKind = match (true) {
            (bool) $legacyIsImage => 'image',
            strcasecmp($legacyExt, 'PDF') === 0 => 'pdf',
            in_array(strtolower($legacyExt), ['ppt', 'pptx', 'odp'], true) => 'ppt',
            in_array(strtolower($legacyExt), ['doc', 'docx', 'odt', 'rtf'], true) => 'doc',
            in_array(strtolower($legacyExt), ['xls', 'xlsx', 'ods', 'csv'], true) => 'sheet',
            in_array(strtolower($legacyExt), ['mp4', 'mov', 'webm', 'mkv'], true) => 'video',
            in_array(strtolower($legacyExt), ['zip', 'rar', '7z', 'tar', 'gz'], true) => 'archive',
            in_array(strtolower($legacyExt), ['html', 'htm', 'css', 'js', 'ts', 'php', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'sh', 'sql', 'json', 'xml', 'yaml', 'yml', 'toml'], true) => 'code',
            in_array(strtolower($legacyExt), ['txt', 'md', 'markdown', 'log', 'rst'], true) => 'text',
            default => 'file',
        };

        $attachmentPayload = $this->attachments
            ->map(fn (MessageAttachment $attachment) => $attachment->toPayload($this))
            ->values()
            ->all();

        if ($attachmentPayload === [] && $legacyFileUrl) {
            $legacySize = $this->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->file_path)
                ? \Illuminate\Support\Facades\Storage::disk('public')->size($this->file_path)
                : null;

            $attachmentPayload = [[
                'id' => null,
                'url' => $legacyFileUrl,
                'preview_url' => ($legacyIsImage || $legacyKind === 'video') ? $legacyFileUrl : null,
                'name' => $legacyName,
                'is_image' => (bool) $legacyIsImage,
                'is_video' => $legacyKind === 'video',
                'kind' => $legacyKind,
                'ext' => $legacyExt,
                'size' => $legacySize,
                'size_label' => MessageAttachment::formatBytes($legacySize),
            ]];
        }

        $firstAttachment = $attachmentPayload[0] ?? null;
        $isEdited = $this->edited_at !== null
            || ($this->updated_at && $this->created_at && $this->updated_at->gt($this->created_at->copy()->addSecond()));

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->display_name ?? $this->user->email,
            'user_role_color' => $this->user->tenantRole?->color,
            'user_initial' => strtoupper(substr($this->user->display_name ?? $this->user->email, 0, 1)),
            'content' => $this->content,
            'is_encrypted' => (bool) $this->is_encrypted,
            'is_markdown' => (bool) $this->is_markdown,
            'is_file' => $this->is_file || count($attachmentPayload) > 0,
            'file_url' => $firstAttachment['url'] ?? $legacyFileUrl,
            'file_preview_url' => $firstAttachment['preview_url'] ?? ($legacyIsImage ? $legacyFileUrl : null),
            'attachments' => $attachmentPayload,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at?->diffForHumans(),
            'created_at_iso' => $this->created_at?->toIso8601String(),
            'updated_at' => $isEdited ? ($this->edited_at ?? $this->updated_at)?->diffForHumans() : null,
            'is_edited' => $isEdited,
            'is_deleted' => false,
            'deleted_by_id' => null,
            'deleted_by_name' => null,
            'deleted_at' => null,
            'reply_count' => $this->relationLoaded('replies') ? $this->replies->count() : 0,
            'mentions_you' => $viewerId
                ? $this->mentions()->where('user_id', $viewerId)->whereNull('read_at')->exists()
                : false,
        ];
    }
}
