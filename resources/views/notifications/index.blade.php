@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
    <div class="max-w-2xl mx-auto space-y-6"
        x-data="{ soundsMuted: (() => { try { return localStorage.getItem('ct_sounds_muted') === '1'; } catch (e) { return false; } })() }"
        x-init="
            const refresh = () => { if (!document.hidden) window.location.reload(); };
            window.addEventListener('ct-unread', refresh);
            if (window.Echo) {
                window.Echo.private('user.{{ (int) auth()->id() }}')
                    .listen('.notifications.unread', refresh);
            }
        ">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-white">Notifications</h1>
                <p class="text-sm text-slate-500 mt-1">Mentions, messages, and workspace updates.</p>
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 shrink-0">
                <button type="button" @click="
                    soundsMuted = !soundsMuted;
                    try { localStorage.setItem('ct_sounds_muted', soundsMuted ? '1' : '0'); } catch (e) {}
                    window.dispatchEvent(new CustomEvent('ct-sounds-muted', { detail: { muted: soundsMuted } }));"
                    class="inline-flex items-center justify-center p-2 rounded-lg hover:bg-white/5 transition"
                    :title="soundsMuted ? 'Unmute notification sounds' : 'Mute notification sounds'"
                    :class="soundsMuted ? 'text-amber-400' : 'text-slate-400 hover:text-white'">
                    <svg x-show="!soundsMuted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                    </svg>
                    <svg x-show="soundsMuted" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                        <path stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
                    </svg>
                </button>
                @include('partials.help-icon', ['hint' => 'Turn notification sounds on or off. This follows you across the app.', 'position' => 'bottom'])
                @include('partials.sort-control', [
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

                    $muteCtx = null;
                    if (
                        ! empty($data['chat_type'])
                        && ! empty($data['chatable_id'])
                        && ! empty($data['author_id'])
                        && (int) $data['author_id'] !== (int) auth()->id()
                    ) {
                        $muteCtx = [
                            'chat_type' => $data['chat_type'],
                            'chatable_id' => (int) $data['chatable_id'],
                            'author_id' => (int) $data['author_id'],
                        ];
                    }
                    $isMuted = $muteCtx ? (bool) ($notificationMutes[$notification->id] ?? false) : false;
                @endphp
                <div class="px-5 py-4 hover:bg-white/3 transition {{ $notification->read_at ? 'opacity-70' : '' }}"
                    x-data="{ expanded: false }">
                    @if($isChatStack)
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ route('notifications.read', $notification->id) }}" class="block min-w-0 flex-1">
                                @include('partials.notification-body', ['data' => $data])
                                <p class="text-[11px] text-slate-600 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                            </a>
                            @if($muteCtx)
                                <button type="button"
                                    x-data="notificationMute(@js($muteCtx), @js($isMuted), @js(route('messages.user-mutes.save', [$muteCtx['chat_type'], $muteCtx['chatable_id']])))"
                                    @click="toggle()"
                                    :title="error || (muted ? 'Unmute notifications from this person in this chat' : 'Mute notifications from this person in this chat')"
                                    class="shrink-0 p-1.5 rounded-lg hover:bg-white/5 transition"
                                    :class="muted ? 'text-amber-400' : 'text-slate-400 hover:text-white'">
                                    <svg x-show="!muted" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                                    </svg>
                                    <svg x-show="muted" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                                        <path stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
                                    </svg>
                                </button>
                            @endif
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
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ ($data['url'] ?? null) ? route('notifications.read', $notification->id) : '#' }}" class="block min-w-0 flex-1">
                                @include('partials.notification-body', ['data' => $data])
                                <p class="text-[11px] text-slate-600 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                            </a>
                            @if($muteCtx)
                                <button type="button"
                                    x-data="notificationMute(@js($muteCtx), @js($isMuted), @js(route('messages.user-mutes.save', [$muteCtx['chat_type'], $muteCtx['chatable_id']])))"
                                    @click="toggle()"
                                    :title="error || (muted ? 'Unmute notifications from this person in this chat' : 'Mute notifications from this person in this chat')"
                                    class="shrink-0 p-1.5 rounded-lg hover:bg-white/5 transition"
                                    :class="muted ? 'text-amber-400' : 'text-slate-400 hover:text-white'">
                                    <svg x-show="!muted" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                                    </svg>
                                    <svg x-show="muted" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                                        <path stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-5 py-12 text-center text-slate-500 text-sm">No notifications yet.</div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>
@endsection

@once
@push('scripts')
@verbatim
<script>
    function notificationMute(ctx, initialMuted, saveUrl) {
        return {
            ctx,
            saveUrl,
            muted: !!initialMuted,
            busy: false,
            error: '',
            async toggle() {
                if (this.busy) return;
                this.busy = true;
                this.error = '';
                const target = !this.muted;
                try {
                    const res = await fetch(this.saveUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            user_ids: [this.ctx.author_id],
                            notifications: target,
                            calls: false,
                            shrink: false,
                        }),
                    });
                    if (!res.ok) {
                        const err = await res.json().catch(() => ({}));
                        throw new Error(err.message || 'Could not update mute.');
                    }
                    this.muted = target;
                } catch (err) {
                    this.error = err?.message || 'Could not update mute.';
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
@endverbatim
@endpush
@endonce
