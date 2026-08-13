<?php

namespace App\Http\Controllers;

use App\Models\ChatUserMute;
use App\Models\Duo;
use App\Models\Group;
use App\Models\MergeSession;
use App\Models\User;
use App\Support\SortsLists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use SortsLists;

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['created_at'],
            'created_at',
            'desc',
        );

        $notifications = $user
            ->notifications()
            ->orderBy('created_at', $dir)
            ->paginate(30);

        $notificationMutes = $this->authorMuteMap($user, $notifications->getCollection());

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications->getCollection()->map(fn ($n) => [
                    'id' => $n->id,
                    'data' => $n->data,
                    'muted' => $notificationMutes[$n->id] ?? false,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                    'created_human' => $n->created_at?->diffForHumans(),
                    'url' => ! empty($n->data['url'] ?? null)
                        ? route('notifications.read', $n->id)
                        : '#',
                ])->values(),
            ]);
        }

        return view('notifications.index', compact('notifications', 'notificationMutes'));
    }

    /**
     * Map of notification id => whether the current user has already muted the
     * notification's author (notifications flag) in the referenced chat.
     */
    private function authorMuteMap(User $user, iterable $notifications): array
    {
        $classMap = [
            'group' => Group::class,
            'duo' => Duo::class,
            'merge' => MergeSession::class,
        ];

        $mutedKeys = ChatUserMute::query()
            ->where('user_id', $user->id)
            ->where('mute_notifications', true)
            ->get()
            ->mapWithKeys(fn (ChatUserMute $mute) => [
                $mute->chatable_type.'|'.$mute->chatable_id.'|'.$mute->muted_user_id => true,
            ]);

        $map = [];
        foreach ($notifications as $notification) {
            $data = $notification->data;
            $authorId = (int) ($data['author_id'] ?? 0);
            $chatType = $data['chat_type'] ?? null;
            $chatableId = (int) ($data['chatable_id'] ?? 0);
            $class = $chatType ? ($classMap[$chatType] ?? null) : null;

            $key = $class && $authorId && $chatableId
                ? $chatType.'|'.$chatableId.'|'.$authorId
                : null;

            $map[$notification->id] = $key ? ($mutedKeys[$key] ?? false) : false;
        }

        return $map;
    }

    public function markRead(string $notification)
    {
        /** @var User $user */
        $user = Auth::user();

        $record = $user->notifications()->where('id', $notification)->firstOrFail();
        $record->markAsRead();

        $data = $record->data;

        if (! empty($data['url'])) {
            return redirect($data['url']);
        }

        $chatType = $data['chat_type'] ?? null;
        $chatableId = $data['chatable_id'] ?? null;

        if ($chatType && $chatableId) {
            return redirect()->route('messages.index', [$chatType, $chatableId]);
        }

        return back();
    }

    public function markAllRead()
    {
        /** @var User $user */
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
