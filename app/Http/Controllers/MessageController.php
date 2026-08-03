<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesChatable;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    use ResolvesChatable;

    public function __construct(private readonly MessageService $messageService)
    {
    }

    public function index(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);

        Gate::authorize('viewAny', [Message::class, $chatable]);

        $messages = Message::where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->whereNull('parent_id')
            ->with(['user', 'replies'])
            ->oldest()
            ->limit(100)
            ->get();

        $initialMessages = $messages->map->toChatPayload()->values();

        return view('messages.index', compact('chatable', 'chatType', 'chatId', 'initialMessages'));
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
            ->with(['user', 'replies']);

        if ($parentId !== null && $parentId !== '') {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        $messages = $query->oldest()->get();

        return response()->json([
            'messages' => $messages->map->toChatPayload()->values(),
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

        $message = $this->messageService->create(
            $chatable,
            $user,
            $credentials['content'] ?? null,
            $request->file('file'),
            $parent
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => $message->toChatPayload()]);
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

        $this->messageService->update(
            $message,
            $credentials['content'] ?? null,
            $request->file('file'),
            $credentials['remove_file'] ?? false,
            $credentials['empty_content'] ?? false
        );

        return redirect()
            ->back()
            ->with('success', 'Message updated successfully.');
    }

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

        $message->load('chatable');
        $thread = $this->messageService->getThread($message);

        $thread['message']->load(['user', 'parent']);
        $thread['replies']->load('user');

        $chatType = match ($message->chatable_type) {
            'group' => 'group',
            'duo' => 'duo',
            'merge' => 'merge',
            default => 'group',
        };

        $initialReplies = $thread['replies']->sortBy('id')->values()->map->toChatPayload()->values();

        return view('messages.thread', array_merge($thread, [
            'chatType' => $chatType,
            'initialReplies' => $initialReplies,
        ]));
    }
}
