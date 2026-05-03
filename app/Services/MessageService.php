<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
            'chatable_type' => $chatable->getMorphClass(),
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
            'message' => $message,
            'replies' => $message->replies()->latest()->get(),
        ];
    }

    public function update(
        Message $message,
        ?string $content = null,
        ?UploadedFile $file = null,
        bool $removeFile = false,
        bool $emptyContent = false
    ): Message {
        $data = [];

        if ($emptyContent) {
            $data['content'] = null;
        } elseif ($content !== null) {
            $data['content'] = $content;
        }

        if ($removeFile && $message->file_path) {
            Storage::disk('public')->delete($message->file_path);
            $data['is_file'] = false;
            $data['file_path'] = null;
        }

        if ($file) {
            if ($message->file_path) {
                Storage::disk('public')->delete($message->file_path);
            }

            $data['is_file'] = true;
            $data['file_path'] = $file->store('messages', 'public');
        }

        $finalContent = array_key_exists('content', $data)
            ? $data['content']
            : $message->content;

        $finalFilePath = array_key_exists('file_path', $data)
            ? $data['file_path']
            : $message->file_path;

        if (blank($finalContent) && blank($finalFilePath)) {
            throw ValidationException::withMessages([
                'content' => ['Message must have content or file.'],
            ]);
        }

        $message->update($data);

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
