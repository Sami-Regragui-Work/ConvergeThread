<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageHide;
use App\Models\User;
use App\Support\MessageEncryption;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(
        private readonly MentionService $mentionService,
        private readonly NotificationStackService $notificationStackService,
        private readonly ChatParticipantService $participantService,
    ) {
    }

    /**
     * @param  UploadedFile|list<UploadedFile>|null  $file
     * @param  list<array{name?:string,mime?:string,iv?:string}>|null  $attachmentMeta
     */
    public function create(
        Group|Duo|MergeSession $chatable,
        User $user,
        ?string $content = null,
        UploadedFile|array|null $file = null,
        ?Message $parent = null,
        string $chatType = 'group',
        ?array $mentionUserIds = null,
        ?array $attachmentMeta = null,
        bool $isMarkdown = false,
    ): Message {
        $files = match (true) {
            $file instanceof UploadedFile => [$file],
            is_array($file) => $file,
            default => [],
        };

        $encrypted = MessageEncryption::isEncrypted($content);

        $data = [
            'chatable_id' => $chatable->id,
            'chatable_type' => $chatable->getMorphClass(),
            'user_id' => $user->id,
            'content' => $content,
            'is_encrypted' => $encrypted,
            'is_markdown' => $isMarkdown,
            'parent_id' => $parent?->id,
            'is_file' => count($files) > 0,
        ];

        if (count($files) === 1 && blank($content)) {
            $data['file_path'] = $files[0]->store('messages', 'public');
        }

        $message = Message::create($data);

        foreach ($files as $index => $uploaded) {
            $meta = is_array($attachmentMeta[$index] ?? null) ? $attachmentMeta[$index] : [];
            $iv = $meta['iv'] ?? null;
            $isFileEncrypted = is_string($iv) && $iv !== '';

            MessageAttachment::create([
                'message_id' => $message->id,
                'file_path' => $uploaded->store('messages', 'public'),
                'original_name' => $meta['name'] ?? $uploaded->getClientOriginalName(),
                'is_encrypted' => $isFileEncrypted,
                'encryption_iv' => $isFileEncrypted ? $iv : null,
                'mime_type' => $meta['mime'] ?? $uploaded->getMimeType(),
                'sort' => $index,
            ]);
        }

        $message->load(['user.tenantRole', 'attachments']);

        if (!$encrypted && $message->attachments->contains(fn ($a) => $a->is_encrypted)) {
            $message->update(['is_encrypted' => true]);
            $message->is_encrypted = true;
        }

        $mentionedIds = $this->mentionService->syncForMessage($message, $chatable, $chatType, $mentionUserIds);

        $this->dispatchChatNotifications($message, $chatable, $chatType, $mentionedIds, $parent);

        MessageSent::dispatch($message);

        return $message;
    }

    private function dispatchChatNotifications(
        Message $message,
        Group|Duo|MergeSession $chatable,
        string $chatType,
        array $mentionedIds,
        ?Message $parent,
    ): void {
        $chatLabel = match (true) {
            $chatable instanceof Group => $chatable->name,
            $chatable instanceof Duo => $chatable->name ?? 'Duo',
            $chatable instanceof MergeSession => 'Merge session',
            default => 'Chat',
        };

        $participants = $this->participantService->participants($chatable);

        foreach ($participants as $participant) {
            if ($participant->id === $message->user_id) {
                continue;
            }

            if (in_array($participant->id, $mentionedIds, true)) {
                continue;
            }

            if ($parent && (int) $parent->user_id === (int) $participant->id) {
                $this->notificationStackService->notifyChatMessage(
                    $message,
                    $chatType,
                    $chatLabel.' (thread)',
                    $participant,
                );
                continue;
            }

            $this->notificationStackService->notifyChatMessage(
                $message,
                $chatType,
                $chatLabel,
                $participant,
            );
        }
    }

    public function getThread(Message $message): array
    {
        return [
            'message' => $message,
            'replies' => $message->replies()->latest()->get(),
        ];
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<int>  $removeAttachmentIds
     * @param  list<array{name?:string,mime?:string,iv?:string}>|null  $attachmentMeta
     */
    public function update(
        Message $message,
        ?string $content = null,
        array $files = [],
        array $removeAttachmentIds = [],
        bool $emptyContent = false,
        bool $removeAllFiles = false,
        ?array $attachmentMeta = null,
    ): Message {
        $data = [];

        if ($emptyContent) {
            $data['content'] = null;
            $data['is_encrypted'] = false;
        } elseif ($content !== null) {
            $data['content'] = $content;
            $data['is_encrypted'] = MessageEncryption::isEncrypted($content);
        }

        $message->loadMissing('attachments');

        if ($removeAllFiles) {
            foreach ($message->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }
            if ($message->file_path) {
                Storage::disk('public')->delete($message->file_path);
            }
            $data['file_path'] = null;
        } elseif ($removeAttachmentIds !== []) {
            $ids = array_map('intval', $removeAttachmentIds);
            $toRemove = $message->attachments->whereIn('id', $ids);
            foreach ($toRemove as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }
        }

        $message->load('attachments');
        $nextSort = (int) ($message->attachments->max('sort') ?? -1) + 1;

        foreach ($files as $index => $uploaded) {
            if (! $uploaded instanceof UploadedFile) {
                continue;
            }
            $meta = is_array($attachmentMeta[$index] ?? null) ? $attachmentMeta[$index] : [];
            $iv = $meta['iv'] ?? null;
            $isFileEncrypted = is_string($iv) && $iv !== '';
            $path = $uploaded->store('messages', 'public');

            MessageAttachment::create([
                'message_id' => $message->id,
                'file_path' => $path,
                'original_name' => $meta['name'] ?? $uploaded->getClientOriginalName(),
                'is_encrypted' => $isFileEncrypted,
                'encryption_iv' => $isFileEncrypted ? $iv : null,
                'mime_type' => $meta['mime'] ?? $uploaded->getMimeType(),
                'sort' => $nextSort + $index,
            ]);
        }

        $message->load('attachments');

        $finalContent = array_key_exists('content', $data)
            ? $data['content']
            : $message->content;

        $hasAttachments = $message->attachments->isNotEmpty();
        $data['is_file'] = $hasAttachments;
        if (! $hasAttachments) {
            $data['file_path'] = null;
        }

        if (blank($finalContent) && ! $hasAttachments) {
            throw ValidationException::withMessages([
                'content' => ['Message must have content or a file.'],
            ]);
        }

        if ($hasAttachments && $message->attachments->contains(fn ($a) => $a->is_encrypted)) {
            $data['is_encrypted'] = true;
        }

        if ($message->attachments->count() > 20) {
            throw ValidationException::withMessages([
                'files' => ['You can attach at most 20 files per message.'],
            ]);
        }

        $data['edited_at'] = now();
        $message->update($data);

        $fresh = $message->fresh(['user.tenantRole', 'attachments', 'deletedBy', 'replies']);
        MessageSent::dispatch($fresh);

        return $fresh;
    }

    public function hideForUser(Message $message, User $user): void
    {
        if ((int) $message->user_id === (int) $user->id) {
            throw ValidationException::withMessages([
                'message' => ['You cannot hide your own message. Delete it instead.'],
            ]);
        }

        MessageHide::query()->firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $user->id,
        ]);
    }

    public function softDeleteForEveryone(Message $message, User $deleter): Message
    {
        $message->loadMissing('attachments');

        foreach ($message->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();
        }

        if ($message->file_path) {
            Storage::disk('public')->delete($message->file_path);
        }

        $message->update([
            'content' => null,
            'is_encrypted' => false,
            'is_file' => false,
            'file_path' => null,
            'deleted_at' => now(),
            'deleted_by_id' => $deleter->id,
        ]);

        $fresh = $message->fresh(['user.tenantRole', 'attachments', 'deletedBy', 'replies']);
        MessageSent::dispatch($fresh);

        return $fresh;
    }

    public function delete(Message $message): bool
    {
        foreach ($message->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        if ($message->is_file && $message->file_path) {
            Storage::disk('public')->delete($message->file_path);
        }

        return $message->delete();
    }
}
