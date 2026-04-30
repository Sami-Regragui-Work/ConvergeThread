<?php

namespace App\Services;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MessageService
{
    public function create(
        int $chatableId,
        string $chatableType,
        User $user,
        string $content,
        ?UploadedFile $file = null,
        ?int $parentId = null
    ): Message {
        $data = [
            'chatable_id' => $chatableId,
            'chatable_type' => $chatableType,
            'user_id' => $user->id,
            'content' => $content,
            'parent_id' => $parentId,
        ];

        if ($file) {
            $data['is_file'] = true;
            $data['filepath'] = $file->store('messages', 'public');
        }

        return Message::create($data);
    }

    public function getThread(Message $message): array
    {
        return [
            'message' => $message->load(['user', 'replies.user']),
            'replies' => $message->replies()->with('user')->latest()->get(),
        ];
    }

    public function delete(Message $message): bool
    {
        if ($message->is_file && $message->filepath) {
            Storage::disk('public')->delete($message->filepath);
        }

        return $message->delete();
    }
}
