@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
    <div class="max-w-2xl mx-auto space-y-6"
        x-data
        x-init="
            const refresh = () => { if (!document.hidden) window.location.reload(); };
            window.addEventListener('ct-unread', refresh);
            if (window.Echo) {
                window.Echo.private('user.{{ (int) auth()->id() }}')
                    .listen('.notifications.unread', refresh);
            }
        ">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-white">Notifications</h1>
                <p class="text-sm text-slate-500 mt-1">Mentions, messages, and workspace updates.</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                @include('partials.sort-control', [
                    'label' => 'Sort',
                    'options' => [
                        'created_at:desc' => 'Newest',
                        'created_at:asc' => 'Oldest',
                    ],
                ])
                @if(auth()->user()->unreadNotifications()->count())
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="text-xs text-brand-400 hover:text-brand-300">Mark all read</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data;
                    $isChatStack = ($data['type'] ?? '') === 'chat_message'
                        && count($data['items'] ?? []) > 1;
                @endphp
                <div class="px-5 py-4 hover:bg-white/3 transition {{ $notification->read_at ? 'opacity-70' : '' }}"
                    x-data="{ expanded: false }">
                    @if($isChatStack)
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('notifications.read', $notification->id) }}" class="block min-w-0">
                                @include('partials.notification-body', ['data' => $data])
                                <p class="text-[11px] text-slate-600 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                            </a>
                            <button type="button" @click="expanded = !expanded" :title="expanded ? 'Hide messages' : 'Show messages'"
                                class="shrink-0 p-1.5 rounded-lg hover:bg-white/5 text-slate-400 transition">
                                <svg class="w-4 h-4 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                        <div x-show="expanded" x-cloak class="mt-2.5 pt-2.5 border-t border-white/5 space-y-1.5">
                            @foreach($data['items'] ?? [] as $item)
                                <a href="{{ route('messages.index', [$data['chat_type'], $data['chatable_id']]) }}?message={{ $item['message_id'] }}"
                                    class="block px-3 py-2 rounded-xl bg-white/3 hover:bg-white/5 transition">
                                    <p class="text-xs text-slate-400 truncate">
                                        <span class="font-semibold text-white">{{ $item['author_name'] ?? 'Someone' }}</span>
                                        <span class="mx-1 text-slate-600">·</span>
                                        <span class="break-words">{{ $item['preview'] ?? '' }}</span>
                                    </p>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <a href="{{ ($data['url'] ?? null) ? route('notifications.read', $notification->id) : '#' }}" class="block">
                            @include('partials.notification-body', ['data' => $data])
                            <p class="text-[11px] text-slate-600 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                        </a>
                    @endif
                </div>
            @empty
                <div class="px-5 py-12 text-center text-slate-500 text-sm">No notifications yet.</div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
@endsection
