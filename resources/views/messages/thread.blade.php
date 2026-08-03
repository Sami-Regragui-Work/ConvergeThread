@extends('layouts.app')
@section('title', 'Thread')

@section('content')
    <div class="max-w-3xl mx-auto flex flex-col h-full max-h-[calc(100vh-8rem)]">
        <div class="flex items-center gap-3 pb-4 border-b border-white/5 mb-4 shrink-0">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('groups.index') }}"
                class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <p class="text-sm font-semibold text-white">Thread</p>
                <p class="text-xs text-slate-500">Discussion around a message</p>
            </div>
        </div>

        {{-- Parent message --}}
        <div class="bg-surface-200 border border-white/5 rounded-2xl p-5 mb-4 shrink-0">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold">
                    {{ strtoupper(substr($message->user->display_name ?? $message->user->email, 0, 1)) }}
                </div>
                <span class="text-sm text-slate-300">{{ $message->user->display_name ?? $message->user->email }}</span>
                <span class="text-xs text-slate-600">{{ $message->created_at->diffForHumans() }}</span>
            </div>

            @if($message->is_file && $message->file_path)
                <a href="{{ asset('storage/' . $message->file_path) }}" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-2 text-brand-400 hover:text-brand-300 text-sm mb-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    View attached file
                </a>
            @endif

            @if($message->content)
                <p class="text-sm text-slate-200 whitespace-pre-wrap">{{ $message->content }}</p>
            @endif
        </div>

        {{-- Replies --}}
        <div class="flex-1 overflow-y-auto space-y-3 pr-1 mb-4" id="replies-container">
            @forelse($replies as $reply)
                <div class="flex gap-3 {{ $reply->user_id === auth()->id() ? 'flex-row-reverse' : '' }}">
                    <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0">
                        {{ strtoupper(substr($reply->user->display_name ?? $reply->user->email, 0, 1)) }}
                    </div>
                    <div class="max-w-[70%] flex flex-col gap-1 {{ $reply->user_id === auth()->id() ? 'items-end' : 'items-start' }}">
                        <div class="px-4 py-2.5 rounded-2xl text-sm {{ $reply->user_id === auth()->id() ? 'bg-brand-500 text-white rounded-tr-sm' : 'bg-surface-100 text-slate-200 rounded-tl-sm' }}">
                            {{ $reply->content }}
                        </div>
                        <span class="text-xs text-slate-600">{{ $reply->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-slate-500 py-8">No replies yet. Start the discussion.</p>
            @endforelse
        </div>

        {{-- Reply form --}}
        @php
            $chatType = match ($message->chatable_type) {
                'group', App\Models\Group::class => 'group',
                'duo', App\Models\Duo::class => 'duo',
                'merge', 'merge_session', App\Models\MergeSession::class => 'merge',
                default => 'group',
            };
        @endphp

        @can('create', [App\Models\Message::class, $message->chatable])
            <div class="pt-4 border-t border-white/5 shrink-0">
                <form method="POST" action="{{ route('messages.store', [$chatType, $message->chatable_id]) }}" class="flex gap-3">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $message->id }}">
                    <input type="text" name="content" required autocomplete="off" placeholder="Reply in thread..."
                        class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500">
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                        Reply
                    </button>
                </form>
            </div>
        @endcan
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.getElementById('replies-container');
                if (el) el.scrollTop = el.scrollHeight;
            });
        </script>
    @endpush
@endsection
