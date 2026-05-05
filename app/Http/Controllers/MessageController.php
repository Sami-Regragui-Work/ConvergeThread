<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messageService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, string $chatType, int $chatId)
    {
        $user = Auth::user();

        $chatable = match ($chatType) {
            'group' => Group::where('tenant_id', $user->tenant_id)->findOrFail($chatId),
            'duo' => Duo::whereHas('group', fn($q) => $q->where('tenant_id', $user->tenant_id))->findOrFail($chatId),
            'merge' => MergeSession::whereHas('groups', fn($q) => $q->where('tenant_id', $user->tenant_id))->findOrFail($chatId),
            default => abort(404, 'Invalid chat type'),
        };

        Gate::authorize('view', [Message::class, $chatable]);

        $messages = Message::where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->latest()
            ->paginate(50);

        return view('messages.index', compact('messages', 'chatable', 'chatType', 'chatId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMessageRequest $request, string $chatType, int $chatId)
    {
        $cridentials = $request->validated();
        $user = Auth::user();

        $chatable = match ($chatType) {
            'group' => Group::where('tenant_id', $user->tenant_id)->findOrFail($chatId),
            'duo' => Duo::whereHas('group', fn($q) => $q->where('tenant_id', $user->tenant_id))
                ->findOrFail($chatId),
            'merge' => MergeSession::whereHas('groups', fn($q) => $q->where('tenant_id', $user->tenant_id))
                ->findOrFail($chatId),
            default => abort(404, 'Invalid chat type'),
        };

        Gate::authorize('create', [Message::class, $chatable]);

        $parent = isset($cridentials['parent_id'])
            ? Message::where('chatable_id', $chatId)
                ->where('chatable_type', $chatable->getMorphClass())
                ->findOrFail($cridentials['parent_id'])
            : null;

        $this->messageService->create(
            $chatable,
            $user,
            $cridentials['content'] ?? null,
            $request->file('file'),
            $parent
        );

        return redirect()
            ->route('messages.index', [$chatType, $chatId])
            ->with('success', 'Message sent successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMessageRequest $request, Message $message)
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

        return redirect()
            ->back()
            ->with('success', 'Message updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Message $message)
    {
        Gate::authorize('delete', $message);

        $this->messageService->delete($message);

        return redirect()
            ->back()
            ->with('success', 'Message deleted successfully.');
    }

    public function thread(Message $message)
    {
        Gate::authorize('thread', $message);

        $thread = $this->messageService->getThread($message);

        $thread['message']->load(['user', 'parent']);
        $thread['replies']->load('user');

        return view('messages.thread', $thread);
    }
}
