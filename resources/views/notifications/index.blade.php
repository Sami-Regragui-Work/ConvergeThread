@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
    <div class="max-w-2xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-white">Notifications</h1>
                <p class="text-sm text-slate-500 mt-1">Mentions, messages, and workspace updates.</p>
            </div>
            @if(auth()->user()->unreadNotifications()->count())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs text-brand-400 hover:text-brand-300">Mark all read</button>
                </form>
            @endif
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
            @forelse($notifications as $notification)
                @php $data = $notification->data; @endphp
                <a href="{{ ($data['url'] ?? null) ? route('notifications.read', $notification->id) : '#' }}"
                    class="block px-5 py-4 hover:bg-white/3 transition {{ $notification->read_at ? 'opacity-70' : '' }}">
                    @if(($data['type'] ?? '') === 'chat_message')
                        <p class="text-sm text-white">
                            Messages from <span class="font-semibold">{{ $data['chat_label'] ?? 'chat' }}</span>
                            @if(($data['stack_count'] ?? 1) > 1)
                                <span class="ml-2 inline-flex min-w-5 h-5 px-1.5 rounded-full bg-brand-500 text-[11px] font-bold items-center justify-center">{{ $data['stack_count'] }}</span>
                            @endif
                        </p>
                        <p class="text-xs text-slate-500 mt-1">{{ $data['preview'] ?? '' }}</p>
                    @elseif(($data['type'] ?? '') === 'added_to_group')
                        <p class="text-sm text-white">Added to group <span class="font-semibold">{{ $data['group_name'] ?? '' }}</span></p>
                        <p class="text-xs text-slate-500 mt-1">By {{ $data['added_by'] ?? 'someone' }}</p>
                    @elseif(($data['type'] ?? '') === 'role_changed')
                        <p class="text-sm text-white">Your role is now <span class="font-semibold">{{ $data['role_name'] ?? '' }}</span></p>
                    @elseif(($data['type'] ?? '') === 'group_permissions')
                        <p class="text-sm text-white">{{ $data['summary'] ?? 'New permissions' }}</p>
                    @elseif(($data['type'] ?? '') === 'merge_session')
                        <p class="text-sm text-white">Merge session started for <span class="font-semibold">{{ $data['group_name'] ?? '' }}</span></p>
                    @else
                        <p class="text-sm text-white">
                            <span class="font-semibold">{{ $data['author_name'] ?? 'Someone' }}</span> mentioned you
                        </p>
                        <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $data['preview'] ?? '' }}</p>
                    @endif
                    <p class="text-[11px] text-slate-600 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </a>
            @empty
                <div class="px-5 py-12 text-center text-slate-500 text-sm">No notifications yet.</div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
@endsection
