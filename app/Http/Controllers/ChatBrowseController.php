<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesChatable;
use App\Models\Message;
use App\Services\ChatBrowseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatBrowseController extends Controller
{
    use ResolvesChatable;

    public function __construct(
        private readonly ChatBrowseService $browseService,
    ) {
    }

    public function chats()
    {
        return response()->json([
            'chats' => $this->browseService->chatsFor(Auth::user()),
        ]);
    }

    public function searchFeed(Request $request, string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        $this->browseService->assertCanBrowse($user, $chatable);

        $after = (int) $request->query('after', 0);
        $limit = (int) $request->query('limit', 100);

        return response()->json(
            $this->browseService->searchFeed($chatable, $after, $limit)
        );
    }

    public function media(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        $this->browseService->assertCanBrowse($user, $chatable);

        return response()->json([
            'sections' => $this->browseService->mediaByThread($chatable),
            'chat' => [
                'type' => $chatType,
                'id' => $chatId,
                'name' => $chatable->name ?? ($chatType.' #'.$chatId),
            ],
        ]);
    }

    public function participants(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        $this->browseService->assertCanBrowse($user, $chatable);

        $people = app(\App\Services\ChatParticipantService::class)
            ->participants($chatable)
            ->map(fn ($u) => [
                'id' => $u->id,
                'username' => $u->username,
                'display_name' => $u->display_name ?? $u->username,
                'label' => $u->displayLabel(),
            ])
            ->values();

        return response()->json(['participants' => $people]);
    }

    public function locate(Message $message)
    {
        $user = Auth::user();
        $message->loadMissing('chatable');
        $this->browseService->assertCanBrowse($user, $message->chatable);

        return response()->json([
            'url' => $this->browseService->locateUrl($message),
            'message_id' => $message->id,
            'parent_id' => $message->parent_id,
        ]);
    }
}
