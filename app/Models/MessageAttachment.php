<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'file_path',
        'original_name',
        'is_encrypted',
        'encryption_iv',
        'mime_type',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

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
        return $this->kind() === 'image';
    }

    public function isVideo(): bool
    {
        return $this->kind() === 'video';
    }

    public function kind(): string
    {
        if ($this->mime_type) {
            if (str_starts_with($this->mime_type, 'image/')) {
                return 'image';
            }
            if (str_starts_with($this->mime_type, 'video/')) {
                return 'video';
            }
            if ($this->mime_type === 'application/pdf') {
                return 'pdf';
            }
        }

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

    public function sizeBytes(): ?int
    {
        if (!$this->file_path || !Storage::disk('public')->exists($this->file_path)) {
            return null;
        }

        return Storage::disk('public')->size($this->file_path);
    }

    public static function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, $unit === 0 ? 0 : 1).' '.$units[$unit];
    }

    public function toPayload(Message $message): array
    {
        $kind = $this->kind();
        $isImage = $kind === 'image';
        $isVideo = $kind === 'video';
        $url = route('messages.attachments.download', [$message, $this]);
        $size = $this->sizeBytes();

        return [
            'id' => $this->id,
            'url' => $url,
            'preview_url' => ($isImage || $isVideo) ? $url : null,
            'name' => $this->displayName(),
            'is_image' => $isImage,
            'is_video' => $isVideo,
            'is_encrypted' => (bool) $this->is_encrypted,
            'encryption_iv' => $this->encryption_iv,
            'mime_type' => $this->mime_type,
            'kind' => $kind,
            'ext' => Str::limit($this->extension(), 5, ''),
            'size' => $size,
            'size_label' => self::formatBytes($size),
        ];
    }
}
