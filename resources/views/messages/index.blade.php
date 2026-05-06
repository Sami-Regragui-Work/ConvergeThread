@extends('layouts.app')
@section('title', 'Messages')

@section('content')
    <div class="flex flex-col h-full max-h-[calc(100vh-8rem)]" x-data="chatPanel()">
        {{-- Context header --}}
        <div class="flex items-center gap-3 pb-4 border-b border-white/5 mb-4 shrink-0">
            <div
                class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-bold shrink-0">
                {{ strtoupper(substr($chatable->name ?? 'M', 0, 1)) }}
            </div>
            <div>
                <p class="text-sm font-semibold text-white">{{ $chatable->name ?? 'Messages' }}</p>
                <p class="text-xs text-slate-500 capitalize">{{ $chatType }}</p>
            </div>
        </div>

        {{-- Messages list --}}
        <div class="flex-1 overflow-y-auto space-y-3 pr-1" id="messages-container">
            @forelse($messages as $message)
                <div class="flex gap-3 {{ $message->user_id === auth()->id() ? 'flex-row-reverse' : '' }}"
                    x-data="{ showActions: false }" @mouseenter="showActions = true" @mouseleave="showActions = false">
                    <div
                        class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0 mt-1">
                        {{ strtoupper(substr($message->user->display_name ?? $message->user->email, 0, 1)) }}
                    </div>
                    <div
                        class="max-w-[70%] flex flex-col gap-1 {{ $message->user_id === auth()->id() ? 'items-end' : 'items-start' }}">
                        <p class="text-xs text-slate-500 {{ $message->user_id === auth()->id() ? 'text-right' : '' }}">
                            {{ $message->user->display_name ?? $message->user->email }}
                        </p>
                        <div class="relative">
                            <div
                                class="px-4 py-2.5 rounded-2xl text-sm {{ $message->user_id === auth()->id() ? 'bg-brand-500 text-white rounded-tr-sm' : 'bg-surface-100 text-slate-200 rounded-tl-sm' }}">
                                {{ $message->content }}
                                @if($message->parent_id)
                                    <span class="block text-xs opacity-60 mt-1">↩ thread</span>
                                @endif
                            </div>
                            @can('delete', $message)
                                <div x-show="showActions" x-cloak
                                    class="absolute {{ $message->user_id === auth()->id() ? 'right-full mr-2' : 'left-full ml-2' }} top-1/2 -translate-y-1/2 flex items-center">
                                    <form method="POST" action="{{ route('messages.destroy', $message) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                            class="p-1.5 rounded-lg bg-surface-300 hover:bg-red-500/20 text-slate-500 hover:text-red-400 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                        <span class="text-xs text-slate-600">{{ $message->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <div class="flex items-center justify-center h-32">
                    <p class="text-slate-500 text-sm">No messages yet.</p>
                </div>
            @endforelse
        </div>

        {{-- Compose --}}
        <div class="pt-4 border-t border-white/5 mt-4 shrink-0">
            @can('create', [App\Models\Message::class, $chatable])
                <form method="POST" action="{{ route('messages.store', [$chatType, $chatId]) }}" class="flex gap-3">
                    @csrf
                    <input type="text" name="content" required autocomplete="off" placeholder="Write a message..."
                        class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500">
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                        Send
                    </button>
                </form>
            @else
                <p class="text-sm text-slate-500 text-center">You don't have permission to send messages here.</p>
            @endcan
        </div>
    </div>

    @push('scripts')
        <script>
            function chatPanel() {
                return {
                    init() {
                        const el = document.getElementById('messages-container');
                        if (el) el.scrollTop = el.scrollHeight;
                    }
                }
            }
        </script>
    @endpush
@endsection