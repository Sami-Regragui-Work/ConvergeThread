@extends('layouts.app')
@section('title', 'Merge Session #' . $session->id)

@section('content')
    <div class="flex flex-col h-full max-h-[calc(100vh-8rem)]" x-data="chatPanel()">
        {{-- Header --}}
        <div class="flex items-center gap-4 pb-4 border-b border-white/5 mb-4 shrink-0">
            <a href="{{ url('/merge-sessions') }}"
                class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div class="flex items-center gap-2">
                <span
                    class="px-3 py-1 bg-brand-500/10 border border-brand-500/20 text-brand-400 text-xs rounded-full font-medium">{{ $session->groupA->name }}</span>
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                <span
                    class="px-3 py-1 bg-brand-500/10 border border-brand-500/20 text-brand-400 text-xs rounded-full font-medium">{{ $session->groupB->name }}</span>
            </div>
            <form method="POST" action="{{ url('/merge-sessions/' . $session->id) }}" class="ml-auto">
                @csrf @method('DELETE')
                <button type="submit" onclick="return confirm('End this merge session?')"
                    class="text-xs text-red-400 hover:text-red-300 transition px-3 py-1.5 rounded-lg hover:bg-red-500/10">
                    End Session
                </button>
            </form>
        </div>

        {{-- Messages --}}
        <div class="flex-1 overflow-y-auto space-y-3 pr-1" id="messages-container">
            @forelse($messages as $message)
                <div class="flex gap-3 {{ $message->user_id === auth()->id() ? 'flex-row-reverse' : '' }}">
                    <div
                        class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0">
                        {{ strtoupper(substr($message->user->display_name ?? $message->user->email, 0, 1)) }}
                    </div>
                    <div
                        class="max-w-[70%] {{ $message->user_id === auth()->id() ? 'items-end' : 'items-start' }} flex flex-col gap-1">
                        <p class="text-xs text-slate-500 {{ $message->user_id === auth()->id() ? 'text-right' : '' }}">
                            {{ $message->user->display_name ?? $message->user->email }}</p>
                        <div
                            class="px-4 py-2.5 rounded-2xl text-sm {{ $message->user_id === auth()->id() ? 'bg-brand-500 text-white rounded-tr-sm' : 'bg-surface-100 text-slate-200 rounded-tl-sm' }}">
                            {{ $message->body }}
                        </div>
                        <span class="text-xs text-slate-600">{{ $message->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            @empty
                <div class="flex items-center justify-center h-full">
                    <p class="text-slate-500 text-sm">No messages yet.</p>
                </div>
            @endforelse
        </div>

        {{-- Input --}}
        <div class="pt-4 border-t border-white/5 mt-4 shrink-0">
            <form method="POST" action="{{ url('/messages') }}" class="flex gap-3">
                @csrf
                <input type="hidden" name="chatable_type" value="merge_session">
                <input type="hidden" name="chatable_id" value="{{ $session->id }}">
                <input type="text" name="body" required autocomplete="off" placeholder="Message..."
                    class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500">
                <button type="submit"
                    class="bg-brand-500 hover:bg-brand-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">Send</button>
            </form>
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