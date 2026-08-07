<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->paginate(30);

        if ($request->wantsJson()) {
            return response()->json([
                'notifications' => $notifications->getCollection()->map(fn ($n) => [
                    'id' => $n->id,
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at?->toIso8601String(),
                    'created_human' => $n->created_at?->diffForHumans(),
                    'url' => ! empty($n->data['url'] ?? null)
                        ? route('notifications.read', $n->id)
                        : '#',
                ])->values(),
            ]);
        }

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(string $notification)
    {
        $record = Auth::user()->notifications()->where('id', $notification)->firstOrFail();
        $record->markAsRead();

        $data = $record->data;

        if (!empty($data['url'])) {
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
        Auth::user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
