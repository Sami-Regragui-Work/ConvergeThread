<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MessageService
{
    public function create(
        Group|Duo|MergeSession $chatable,
        User $user,
        ?string $content = null,
        ?UploadedFile $file = null,
        ?Message $parent = null
    ): Message {
        $data = [
            'chatable_id' => $chatable->id,
            'chatable_type' => $chatable::class,
            'user_id' => $user->id,
            'content' => $content,
            'parent_id' => $parent?->id,
        ];

        if ($file) {
            $data['is_file'] = true;
            $data['file_path'] = $file->store('messages', 'public');
        }

        return Message::create($data);
    }

    public function getThread(Message $message): array
    {
        return [
            'message' => $message/*->load(['user', 'replies.user'])*/,
            'replies' => $message->replies()->latest()->get(),
        ];
    }

    public function update(Message $message, string $content): Message
    {
        $message->update(['content' => $content]);
        return $message->fresh();
    }

    public function delete(Message $message): bool
    {
        if ($message->is_file && $message->file_path) {
            Storage::disk('public')->delete($message->file_path);
        }

        return $message->delete();
    }
}
