<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'file_path',
        'original_name',
        'sort',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function displayName(): string
    {
        return $this->original_name ?: basename($this->file_path);
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->displayName(), PATHINFO_EXTENSION) ?: 'FILE');
    }

    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $this->displayName())
            || (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $this->file_path);
    }

    public function kind(): string
    {
        $ext = strtolower(pathinfo($this->displayName(), PATHINFO_EXTENSION)
            ?: pathinfo($this->file_path, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'image',
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['ppt', 'pptx', 'odp'], true) => 'ppt',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true) => 'doc',
            in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true) => 'sheet',
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true) => 'archive',
            in_array($ext, ['mp3', 'wav', 'ogg', 'm4a'], true) => 'audio',
            in_array($ext, ['mp4', 'mov', 'webm', 'mkv'], true) => 'video',
            default => 'file',
        };
    }

    public function toPayload(Message $message): array
    {
        $isImage = $this->isImage();
        $url = route('messages.attachments.download', [$message, $this]);

        return [
            'id' => $this->id,
            'url' => $url,
            'preview_url' => $isImage ? $url : null,
            'name' => $this->displayName(),
            'is_image' => $isImage,
            'kind' => $this->kind(),
            'ext' => Str::limit($this->extension(), 5, ''),
        ];
    }
}
