<?php

namespace App\Services;

use App\Models\Duo;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ChatBrowseService
{
    /**
     * @return list<array{type:string,id:int,name:string,kind:string}>
     */
    public function chatsFor(User $user): array
    {
        if ($user->isOwner() || !$user->tenant_id) {
            return [];
        }

        $chats = [];

        $memberGroupIds = GroupMember::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->pluck('group_id');

        Group::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('id', $memberGroupIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (Group $group) use (&$chats) {
                $chats[] = [
                    'type' => 'group',
                    'id' => $group->id,
                    'name' => $group->name,
                    'kind' => 'group',
                ];
            });

        Duo::query()
            ->where(function ($q) use ($user) {
                $q->where('user1_id', $user->id)->orWhere('user2_id', $user->id);
            })
            ->whereHas('group', fn ($q) => $q->where('tenant_id', $user->tenant_id))
            ->with(['user1:id,display_name,username', 'user2:id,display_name,username', 'group:id,name'])
            ->get()
            ->each(function (Duo $duo) use (&$chats, $user) {
                $other = (int) $duo->user1_id === (int) $user->id ? $duo->user2 : $duo->user1;
                $chats[] = [
                    'type' => 'duo',
                    'id' => $duo->id,
                    'name' => $other?->displayLabel() ?? ('Duo #'.$duo->id),
                    'kind' => 'duo',
                ];
            });

        MergeSession::query()
            ->forTenant((int) $user->tenant_id)
            ->whereNull('ended_at')
            ->whereHas('groups', function ($q) use ($memberGroupIds) {
                $q->whereIn('groups.id', $memberGroupIds);
            })
            ->with('groups:id,name')
            ->get()
            ->each(function (MergeSession $session) use (&$chats) {
                $chats[] = [
                    'type' => 'merge',
                    'id' => $session->id,
                    'name' => $session->name,
                    'kind' => 'merge',
                ];
            });

        return $chats;
    }

    /**
     * Cursor-paginated messages for client-side decrypt + search indexing.
     *
     * @return array{messages: list<array<string, mixed>>, next_after: ?int}
     */
    public function searchFeed(Group|Duo|MergeSession $chatable, int $afterId = 0, int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));

        $rows = Message::query()
            ->where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->with(['user:id,display_name,email,username', 'attachments'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $messages = $rows->map(function (Message $message) {
            $names = $message->attachments->map(fn (MessageAttachment $a) => $a->displayName())->values()->all();
            if ($names === [] && $message->is_file && $message->file_path) {
                $names[] = basename($message->file_path);
            }

            return [
                'id' => $message->id,
                'parent_id' => $message->parent_id,
                'user_id' => $message->user_id,
                'user_name' => $message->user?->displayLabel() ?? 'User',
                'content' => $message->content,
                'is_encrypted' => (bool) $message->is_encrypted,
                'created_at' => $message->created_at?->toIso8601String(),
                'attachment_names' => $names,
                'has_attachments' => count($names) > 0,
            ];
        })->values()->all();

        $last = $rows->last();

        return [
            'messages' => $messages,
            'next_after' => $last && $rows->count() === $limit ? $last->id : null,
        ];
    }

    /**
     * Attachments grouped by root thread (root message or its parent chain).
     *
     * @return list<array{root_id:int,root_preview:?string,root_user_name:?string,root_created_at:?string,files:list<array<string,mixed>>}>
     */
    public function mediaByThread(Group|Duo|MergeSession $chatable): array
    {
        $messages = Message::query()
            ->where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->with(['user:id,display_name,email,username', 'attachments', 'parent:id,parent_id,content,user_id,is_encrypted'])
            ->orderBy('id')
            ->get();

        $byId = $messages->keyBy('id');
        $groups = [];

        foreach ($messages as $message) {
            $attachments = $message->attachments;
            $legacy = [];
            if ($attachments->isEmpty() && $message->is_file && $message->file_path) {
                $legacy[] = null;
            }
            if ($attachments->isEmpty() && $legacy === []) {
                continue;
            }

            $rootId = $message->parent_id ? (int) $message->parent_id : (int) $message->id;
            // Only one level of nesting in this app
            if ($message->parent_id && $byId->has($message->parent_id)) {
                $parent = $byId->get($message->parent_id);
                if ($parent->parent_id) {
                    $rootId = (int) $parent->parent_id;
                }
            }

            if (!isset($groups[$rootId])) {
                $root = $byId->get($rootId) ?? $message;
                $groups[$rootId] = [
                    'root_id' => $rootId,
                    'root_preview' => $root->is_encrypted ? null : $root->content,
                    'root_is_encrypted' => (bool) $root->is_encrypted,
                    'root_content' => $root->content,
                    'root_user_name' => $root->user?->displayLabel(),
                    'root_created_at' => $root->created_at?->toIso8601String(),
                    'files' => [],
                ];
            }

            foreach ($attachments as $attachment) {
                $payload = $attachment->toPayload($message);
                $payload['message_id'] = $message->id;
                $payload['parent_id'] = $message->parent_id;
                $payload['user_name'] = $message->user?->displayLabel();
                $payload['created_at'] = $message->created_at?->toIso8601String();
                $groups[$rootId]['files'][] = $payload;
            }

            if ($attachments->isEmpty() && $message->is_file && $message->file_path) {
                $url = route('messages.attachment', $message);
                $groups[$rootId]['files'][] = [
                    'id' => null,
                    'message_id' => $message->id,
                    'parent_id' => $message->parent_id,
                    'url' => $url,
                    'preview_url' => preg_match('/\.(jpe?g|png|gif|webp|mp4|webm|mov)$/i', $message->file_path) ? $url : null,
                    'name' => basename($message->file_path),
                    'is_image' => (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $message->file_path),
                    'is_video' => (bool) preg_match('/\.(mp4|webm|mov|mkv)$/i', $message->file_path),
                    'is_encrypted' => false,
                    'kind' => 'file',
                    'user_name' => $message->user?->displayLabel(),
                    'created_at' => $message->created_at?->toIso8601String(),
                ];
            }
        }

        return array_values($groups);
    }

    public function locateUrl(Message $message): string
    {
        $message->loadMissing('chatable');
        $chatable = $message->chatable;
        $type = match (true) {
            $chatable instanceof Group => 'group',
            $chatable instanceof Duo => 'duo',
            $chatable instanceof MergeSession => 'merge',
            default => 'group',
        };

        if ($message->parent_id) {
            return route('messages.thread', $message->parent_id).'?message='.$message->id;
        }

        return route('messages.index', [$type, $chatable->id]).'?message='.$message->id;
    }

    public function assertCanBrowse(User $user, Group|Duo|MergeSession $chatable): void
    {
        Gate::forUser($user)->authorize('viewAny', [Message::class, $chatable]);
    }
}
