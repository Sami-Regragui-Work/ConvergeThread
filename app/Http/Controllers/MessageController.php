<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesChatable;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\TenantRole;
use App\Services\ChatParticipantService;
use App\Services\MentionService;
use App\Services\MessageService;
use App\Services\NotificationStackService;
use App\Support\MessageEncryption;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    use ResolvesChatable;

    public function __construct(
        private readonly MessageService $messageService,
        private readonly MentionService $mentionService,
        private readonly NotificationStackService $notificationStackService,
    ) {
    }

    private function payload(
        Message $message,
        ?int $viewerId = null,
        ?array $renderContext = null,
        ?string $chatType = null,
        Group|Duo|MergeSession|null $chatable = null,
    ): array {
        $payload = $message->toChatPayload($viewerId);
        $ctx = $renderContext ?? ['roleColors' => [], 'usernameLabels' => [], 'mergeUserLabels' => []];

        if ($payload['content'] && !($payload['is_encrypted'] ?? false) && !MessageEncryption::isEncrypted($payload['content'])) {
            $payload['content_html'] = $this->mentionService->renderContentHtml(
                $payload['content'],
                $ctx['roleColors'],
                $ctx['usernameLabels'],
                $ctx['mergeUserLabels'],
            );
        } else {
            $payload['content_html'] = null;
        }

        $message->loadMissing(['chatable', 'user']);

        if ($chatType === 'merge' && $chatable) {
            $payload['user_name'] = app(ChatParticipantService::class)
                ->displayLabelFor($message->user, $chatable, $chatType);
        }

        $viewer = $viewerId ? Auth::user() : null;
        $payload['can_edit'] = $viewer
            && (int) $message->user_id === (int) $viewerId
            && Gate::forUser($viewer)->allows('update', $message);
        $payload['can_delete'] = $viewer
            && Gate::forUser($viewer)->allows('delete', $message);

        return $payload;
    }

    private function renderContextFor(Group|Duo|MergeSession $chatable, string $chatType, ?int $tenantId): array
    {
        return $this->mentionService->renderContextForChatable($chatable, $chatType, $tenantId);
    }

    private function mapParticipants(Group|Duo|MergeSession $chatable): Collection
    {
        return app(ChatParticipantService::class)
            ->participants($chatable)
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'display_name' => $u->display_name ?? $u->username,
                'role' => $u->tenantRole?->name,
                'role_color' => $u->tenantRole?->color,
            ])->values();
    }

    /**
     * @param  Collection<int, array{id:int,username:string,display_name:string,role:?string,role_color:?string}>  $participants
     * @return list<array{token:string,label:string,color:?string,special?:string}>
     */
    private function mentionSuggestions(
        Group|Duo|MergeSession $chatable,
        Collection $participants,
        int $viewerId,
        string $chatType,
        ?int $tenantId = null,
    ): array {
        $participantService = app(ChatParticipantService::class);
        $roleColors = TenantRole::query()
            ->when($tenantId, fn ($q) => $q->forTenant($tenantId))
            ->pluck('color', 'name');

        $suggestions = [
            ['token' => '@all', 'label' => 'Everyone in this chat', 'color' => null],
            ['token' => '@selected', 'label' => 'Pick specific people…', 'special' => 'selected', 'color' => null],
        ];

        if ($chatType === 'merge') {
            foreach ($participantService->groupsInChat($chatable) as $group) {
                $suggestions[] = [
                    'token' => '@group:'.$group->name,
                    'label' => 'Group · '.$group->name,
                    'color' => null,
                ];

                foreach ($participantService->participants($group) as $user) {
                    if ((int) $user->id === $viewerId) {
                        continue;
                    }

                    $suggestions[] = [
                        'token' => '@'.$group->name.'.'.$user->username,
                        'label' => $user->display_name ?? $user->username,
                        'color' => $user->tenantRole?->color,
                    ];
                }
            }
        } else {
            foreach ($participants->pluck('role')->filter()->unique() as $role) {
                $suggestions[] = [
                    'token' => '@role:'.$role,
                    'label' => 'Role · '.$role,
                    'color' => $roleColors[$role] ?? null,
                ];
            }

            foreach ($participants as $participant) {
                if ((int) $participant['id'] === $viewerId) {
                    continue;
                }

                $suggestions[] = [
                    'token' => '@'.$participant['username'],
                    'label' => $participant['display_name'],
                    'color' => $participant['role_color'] ?? null,
                ];
            }
        }

        if ($chatType === 'merge') {
            foreach ($participants->pluck('role')->filter()->unique() as $role) {
                $suggestions[] = [
                    'token' => '@role:'.$role,
                    'label' => 'Role · '.$role,
                    'color' => $roleColors[$role] ?? null,
                ];
            }
        }

        return $suggestions;
    }

    public function index(Request $request, string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);

        Gate::authorize('viewAny', [Message::class, $chatable]);

        $focusId = (int) $request->query('message', 0);
        if ($focusId > 0) {
            $focus = Message::query()
                ->where('chatable_type', $chatable->getMorphClass())
                ->where('chatable_id', $chatable->id)
                ->where('id', $focusId)
                ->first();

            if ($focus?->parent_id) {
                return redirect()->to(
                    route('messages.thread', $focus->parent_id).'?message='.$focus->id
                );
            }
        }

        $messages = Message::where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->whereNull('parent_id')
            ->with(['user.tenantRole', 'attachments', 'replies'])
            ->oldest()
            ->limit(100)
            ->get();

        $renderContext = $this->renderContextFor($chatable, $chatType, $user->tenant_id);
        $initialMessages = $messages->map(fn (Message $m) => $this->payload($m, $user->id, $renderContext, $chatType, $chatable))->values();

        $participants = $this->mapParticipants($chatable);

        $mentionIds = $this->mentionService->unreadMessageIdsForUser($user, $chatable);
        $mentionSuggestions = $this->mentionSuggestions($chatable, $participants, $user->id, $chatType, $user->tenant_id);
        $chatMuted = $this->notificationStackService->isChatMuted(
            $user,
            $chatable->getMorphClass(),
            $chatable->id,
        );
        $activeCall = app(\App\Services\CallSessionService::class)->active($chatType, (int) $chatId);

        return view('messages.index', compact(
            'chatable',
            'chatType',
            'chatId',
            'initialMessages',
            'participants',
            'mentionIds',
            'mentionSuggestions',
            'chatMuted',
            'activeCall',
        ));
    }

    public function poll(Request $request, string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);

        Gate::authorize('viewAny', [Message::class, $chatable]);

        $afterId = (int) $request->query('after', 0);
        $parentId = $request->query('parent_id');

        $query = Message::where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->where('id', '>', $afterId)
            ->with(['user.tenantRole', 'attachments', 'replies']);

        if ($parentId !== null && $parentId !== '') {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        $messages = $query->oldest()->get();
        $renderContext = $this->renderContextFor($chatable, $chatType, $user->tenant_id);

        return response()->json([
            'messages' => $messages->map(fn (Message $m) => $this->payload($m, $user->id, $renderContext, $chatType, $chatable))->values(),
        ]);
    }

    public function store(StoreMessageRequest $request, string $chatType, int $chatId)
    {
        $credentials = $request->validated();
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);

        Gate::authorize('create', [Message::class, $chatable]);

        $parent = isset($credentials['parent_id'])
            ? Message::where('chatable_id', $chatable->id)
                ->where('chatable_type', $chatable->getMorphClass())
                ->findOrFail($credentials['parent_id'])
            : null;

        $files = array_values(array_filter($request->file('files', []) ?? []));
        $singleFile = $request->file('file');
        if ($singleFile) {
            $files[] = $singleFile;
        }

        $mentionUserIds = $credentials['mention_user_ids'] ?? null;
        $attachmentMeta = $credentials['attachment_meta'] ?? null;

        $message = $this->messageService->create(
            $chatable,
            $user,
            $credentials['content'] ?? null,
            $files !== [] ? $files : null,
            $parent,
            $chatType,
            $mentionUserIds,
            $attachmentMeta,
        );

        $renderContext = $this->renderContextFor($chatable, $chatType, $user->tenant_id);

        if ($request->wantsJson()) {
            return response()->json(['message' => $this->payload($message, $user->id, $renderContext, $chatType, $chatable)]);
        }

        if ($parent) {
            return redirect()
                ->route('messages.thread', $parent)
                ->with('success', 'Reply sent successfully.');
        }

        return redirect()
            ->route('messages.index', [$chatType, $chatId])
            ->with('success', 'Message sent successfully.');
    }

    public function update(UpdateMessageRequest $request, Message $message)
    {
        $credentials = $request->validated();
        Gate::authorize('update', $message);

        $message->loadMissing('chatable');
        $chatType = match ($message->chatable_type) {
            'group' => 'group',
            'duo' => 'duo',
            'merge' => 'merge',
            default => 'group',
        };

        $this->messageService->update(
            $message,
            $credentials['content'] ?? null,
            $request->file('file'),
            $credentials['remove_file'] ?? false,
            $credentials['empty_content'] ?? false
        );

        $user = Auth::user();
        $renderContext = $this->renderContextFor($message->chatable, $chatType, $user->tenant_id);
        $fresh = $message->fresh(['user.tenantRole', 'attachments']);

        if ($request->wantsJson()) {
            return response()->json(['message' => $this->payload($fresh, $user->id, $renderContext, $chatType, $message->chatable)]);
        }

        return redirect()
            ->back()
            ->with('success', 'Message updated successfully.');
    }

    public function destroy(\Illuminate\Http\Request $request, Message $message)
    {
        Gate::authorize('delete', $message);

        $message->loadMissing('chatable');
        $chatType = match ($message->chatable_type) {
            'group' => 'group',
            'duo' => 'duo',
            'merge' => 'merge',
            default => 'group',
        };
        $chatId = $message->chatable_id;
        $wasRoot = $message->parent_id === null;
        $messageId = $message->id;

        $this->messageService->delete($message);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $messageId,
                'redirect' => $wasRoot
                    ? route('messages.index', [$chatType, $chatId])
                    : null,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Message deleted successfully.');
    }

    public function thread(Message $message)
    {
        Gate::authorize('thread', $message);

        $message->load('chatable');
        $thread = $this->messageService->getThread($message);

        $thread['message']->load(['user.tenantRole', 'parent', 'attachments']);
        $thread['replies']->load(['user.tenantRole', 'attachments']);

        $chatType = match ($message->chatable_type) {
            'group' => 'group',
            'duo' => 'duo',
            'merge' => 'merge',
            default => 'group',
        };

        $user = Auth::user();
        $chatable = $message->chatable;
        $participants = $this->mapParticipants($chatable);
        $renderContext = $this->renderContextFor($chatable, $chatType, $user->tenant_id);

        $initialReplies = $thread['replies']->sortBy('id')->values()
            ->map(fn (Message $m) => $this->payload($m, $user->id, $renderContext, $chatType, $chatable))->values();

        $mentionIds = $this->mentionService->unreadMessageIdsForUser($user, $chatable);
        $mentionSuggestions = $this->mentionSuggestions($chatable, $participants, $user->id, $chatType, $user->tenant_id);
        $parentPayload = $this->payload($thread['message'], $user->id, $renderContext, $chatType, $chatable);
        $threadMuted = $this->notificationStackService->isThreadMuted($user, $message->id);

        return view('messages.thread', array_merge($thread, [
            'chatType' => $chatType,
            'initialReplies' => $initialReplies,
            'participants' => $participants,
            'mentionIds' => $mentionIds,
            'mentionSuggestions' => $mentionSuggestions,
            'parentPayload' => $parentPayload,
            'threadMuted' => $threadMuted,
        ]));
    }

    public function attachment(Message $message): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('view', $message);

        abort_unless($message->is_file && $message->file_path, 404);
        abort_unless(Storage::disk('public')->exists($message->file_path), 404);

        return $this->streamPublicFile(
            $message->file_path,
            basename($message->file_path),
            (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $message->file_path),
        );
    }

    public function downloadAttachment(Message $message, MessageAttachment $attachment): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('view', $message);
        abort_unless((int) $attachment->message_id === (int) $message->id, 404);
        abort_unless(Storage::disk('public')->exists($attachment->file_path), 404);

        return $this->streamPublicFile(
            $attachment->file_path,
            $attachment->original_name ?? basename($attachment->file_path),
            $attachment->isImage() || $attachment->isVideo(),
        );
    }

    private function streamPublicFile(string $path, string $name, bool $inlineImage = false): BinaryFileResponse|StreamedResponse
    {
        $absolute = Storage::disk('public')->path($path);

        if ($inlineImage) {
            return response()->file($absolute, [
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]);
        }

        return response()->download($absolute, $name);
    }

    public function mentions(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        return response()->json([
            'message_ids' => $this->mentionService->unreadMessageIdsForUser($user, $chatable),
        ]);
    }

    public function markMentionRead(Message $message)
    {
        Gate::authorize('view', $message);
        $this->mentionService->markMessageReadForUser($message->id, Auth::user());

        return response()->json(['ok' => true]);
    }

    public function toggleMute(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        $muted = $this->notificationStackService->toggleChatMute(
            $user,
            $chatable->getMorphClass(),
            $chatable->id,
        );

        return back()->with('success', $muted ? 'Chat notifications muted.' : 'Chat notifications unmuted.');
    }

    public function toggleThreadMute(Message $message)
    {
        Gate::authorize('thread', $message);

        $muted = $this->notificationStackService->toggleThreadMute(Auth::user(), $message->id);

        return back()->with('success', $muted ? 'Thread notifications muted.' : 'Thread notifications unmuted.');
    }
}
