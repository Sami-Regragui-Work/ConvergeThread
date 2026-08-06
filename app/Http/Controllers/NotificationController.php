<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->paginate(30);

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
