<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMessageRequest;
use App\Http\Requests\Api\UpdateMessageRequest;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messageService)
    {
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMessageRequest $request, string $chatType, int $chatId): JsonResponse
    {
        $cridentials = $request->validated();
        $user = $request->user();

        $chatable = match ($chatType) {
            'group' => Group::where('tenant_id', $user->tenant_id)->findOrFail($chatId),
            'duo' => Duo::whereHas('group', fn($q) => $q->where('tenant_id', $user->tenant_id))
                ->findOrFail($chatId),
            'merge' => MergeSession::whereHas('groups', fn($q) => $q->where('tenant_id', $user->tenant_id))
                ->findOrFail($chatId),
            default => abort(404, 'Invalid chat type'),
        };

        Gate::authorize('createMessage', $chatable);

        $parent = isset($cridentials['parent_id'])
            ? Message::where('chatable_id', $chatId)
                ->where('chatable_type', $chatable->getMorphClass())
                ->findOrFail($cridentials['parent_id'])
            : null;

        $message = $this->messageService->create(
            $chatable,
            $user,
            $cridentials['content'] ?? null,
            $request->file('file'),
            $parent
        );

        return response()->json($message->load(['user', 'parent']), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $cridentials = $request->validated();
        Gate::authorize('update', $message);

        $this->messageService->update(
            $message,
            $cridentials['content'] ?? null,
            $request->file('file'),
            $cridentials['remove_file'] ?? false,
            $cridentials['empty_content'] ?? false
        );

        return response()->json($message->fresh()->load('user'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Message $message): JsonResponse
    {
        Gate::authorize('delete', $message);

        $this->messageService->delete($message);

        return response()->json(null, 204);
    }

    public function thread(Message $message): JsonResponse
    {
        Gate::authorize('view', $message);
        
        $thread = $this->messageService->getThread($message);

        $thread['message']->load(['user', 'parent']);
        $thread['replies']->load('user');

        return response()->json($thread);
    }
}
