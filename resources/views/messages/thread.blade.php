@extends('layouts.app')
@section('title', 'Thread')

@php
    $breadcrumbs = [
        ['label' => 'Groups', 'url' => route('groups.index')],
        ['label' => 'Thread'],
    ];
@endphp

@section('content')
    <div class="max-w-3xl mx-auto flex flex-col h-full max-h-[calc(100vh-8rem)]"
        x-data="threadPanel({
            replies: @js($initialReplies),
            pollUrl: @js(route('messages.poll', [$chatType, $message->chatable_id]) . '?parent_id=' . $message->id),
            storeUrl: @js(route('messages.store', [$chatType, $message->chatable_id])),
            parentId: @js($message->id),
            currentUserId: @js(auth()->id()),
            canReply: @js(auth()->user()->can('create', [App\Models\Message::class, $message->chatable])),
        })"
        x-init="init()">

        <div class="flex items-center gap-3 pb-4 border-b border-white/5 mb-4 shrink-0">
            <div>
                <p class="text-sm font-semibold text-white">Thread</p>
                <p class="text-xs text-slate-500">Live replies every 3s</p>
            </div>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl p-5 mb-4 shrink-0">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold">
                    {{ strtoupper(substr($message->user->display_name ?? $message->user->email, 0, 1)) }}
                </div>
                <span class="text-sm text-slate-300">{{ $message->user->display_name ?? $message->user->email }}</span>
                <span class="text-xs text-slate-600">{{ $message->created_at->diffForHumans() }}</span>
            </div>

            @if($message->is_file && $message->file_path)
                <a href="{{ route('messages.attachment', $message) }}" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-2 text-brand-400 hover:text-brand-300 text-sm mb-2">
                    View attached file
                </a>
            @endif

            @if($message->content)
                <p class="text-sm text-slate-200 whitespace-pre-wrap">{{ $message->content }}</p>
            @endif
        </div>

        <div class="flex-1 overflow-y-auto space-y-3 pr-1 mb-4" x-ref="repliesContainer">
            <template x-if="replies.length === 0">
                <p class="text-center text-sm text-slate-500 py-8">No replies yet. Start the discussion.</p>
            </template>

            <template x-for="reply in replies" :key="reply.id">
                <div class="flex gap-3" :class="reply.user_id === currentUserId ? 'flex-row-reverse' : ''">
                    <div class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0"
                        x-text="reply.user_initial"></div>
                    <div class="max-w-[85%] sm:max-w-[70%] flex flex-col gap-1"
                        :class="reply.user_id === currentUserId ? 'items-end' : 'items-start'">
                        <div class="px-4 py-2.5 rounded-2xl text-sm wrap-break-word"
                            :class="reply.user_id === currentUserId ? 'bg-brand-500 text-white rounded-tr-sm' : 'bg-surface-100 text-slate-200 rounded-tl-sm'"
                            x-text="reply.content"></div>
                        <span class="text-xs text-slate-600" x-text="reply.created_at"></span>
                    </div>
                </div>
            </template>
        </div>

        <template x-if="canReply">
            <div class="pt-4 border-t border-white/5 shrink-0">
                <form @submit.prevent="sendReply" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" x-model="draft" required autocomplete="off" placeholder="Reply in thread..."
                        :disabled="sending"
                        class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500 disabled:opacity-50">
                    <button type="submit" :disabled="sending || !draft.trim()"
                        class="bg-brand-500 hover:bg-brand-600 disabled:opacity-50 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                        <span x-text="sending ? 'Sending…' : 'Reply'"></span>
                    </button>
                </form>
            </div>
        </template>
    </div>

    @push('scripts')
        <script>
            function threadPanel(config) {
                return {
                    replies: config.replies ?? [],
                    pollUrl: config.pollUrl,
                    storeUrl: config.storeUrl,
                    parentId: config.parentId,
                    currentUserId: config.currentUserId,
                    canReply: config.canReply,
                    draft: '',
                    sending: false,
                    pollTimer: null,

                    init() {
                        this.scrollToBottom();
                        this.pollTimer = setInterval(() => this.poll(), 3000);
                    },

                    lastId() {
                        if (!this.replies.length) return 0;
                        return Math.max(...this.replies.map(r => r.id));
                    },

                    scrollToBottom() {
                        this.$nextTick(() => {
                            const el = this.$refs.repliesContainer;
                            if (el) el.scrollTop = el.scrollHeight;
                        });
                    },

                    appendReplies(incoming) {
                        const existing = new Set(this.replies.map(r => r.id));
                        let added = false;

                        for (const reply of incoming) {
                            if (!existing.has(reply.id)) {
                                this.replies.push(reply);
                                added = true;
                            }
                        }

                        if (added) this.scrollToBottom();
                    },

                    async poll() {
                        try {
                            const response = await fetch(`${this.pollUrl}&after=${this.lastId()}`, {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) return;

                            const data = await response.json();
                            this.appendReplies(data.messages ?? []);
                        } catch (error) {
                            //
                        }
                    },

                    async sendReply() {
                        if (this.sending || !this.draft.trim()) return;

                        this.sending = true;
                        const formData = new FormData();
                        formData.append('content', this.draft.trim());
                        formData.append('parent_id', this.parentId);

                        try {
                            const response = await fetch(this.storeUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                credentials: 'same-origin',
                                body: formData,
                            });

                            if (!response.ok) return;

                            const data = await response.json();
                            this.appendReplies([data.message]);
                            this.draft = '';
                        } finally {
                            this.sending = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endsection
