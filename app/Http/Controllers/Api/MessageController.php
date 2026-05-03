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

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $service)
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

        $parent = isset($cridentials['parent_id'])
            ? Message::where('chatable_id', $chatId)
                ->where('chatable_type', $chatable->getMorphClass())
                ->findOrFail($cridentials['parent_id'])
            : null;

        $message = $this->service->create(
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

        $this->service->update($message, $cridentials['content']);

        return response()->json($message->fresh()->load('user'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Message $message): JsonResponse
    {
        $this->service->delete($message);

        return response()->json(null, 204);
    }

    public function thread(Message $message): JsonResponse
    {
        $thread = $this->service->getThread($message);

        $thread['message']->load(['user', 'parent']);
        $thread['replies']->load('user');

        return response()->json($thread);
    }
}
