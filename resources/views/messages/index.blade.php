@extends('layouts.app')
@section('title', 'Messages')

@php
    $breadcrumbs = [
        ['label' => 'Groups', 'url' => route('groups.index')],
        ['label' => $chatable->name ?? 'Chat'],
    ];
@endphp

@section('content')
    <div class="flex flex-col h-full max-h-[calc(100vh-8rem)]"
        x-data="chatPanel({
            messages: @js($initialMessages),
            pollUrl: @js(route('messages.poll', [$chatType, $chatId])),
            storeUrl: @js(route('messages.store', [$chatType, $chatId])),
            threadUrlTemplate: @js(preg_replace('/\/0(\/thread)$/', '/__ID__$1', route('messages.thread', 0))),
            currentUserId: @js(auth()->id()),
            canSend: @js(auth()->user()->can('create', [App\Models\Message::class, $chatable])),
        })"
        x-init="init()">

        <div class="flex items-center gap-3 pb-4 border-b border-white/5 mb-4 shrink-0">
            <div
                class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-bold shrink-0">
                {{ strtoupper(substr($chatable->name ?? 'M', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white truncate">{{ $chatable->name ?? 'Messages' }}</p>
                <p class="text-xs text-slate-500 capitalize">{{ $chatType }} · Live updates every 3s</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto space-y-3 pr-1" x-ref="messagesContainer">
            <template x-if="messages.length === 0">
                <div class="flex items-center justify-center h-32">
                    <p class="text-slate-500 text-sm">No messages yet. Say hello!</p>
                </div>
            </template>

            <template x-for="message in messages" :key="message.id">
                <div class="flex gap-3" :class="message.user_id === currentUserId ? 'flex-row-reverse' : ''">
                    <div
                        class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0 mt-1"
                        x-text="message.user_initial"></div>
                    <div class="max-w-[85%] sm:max-w-[70%] flex flex-col gap-1"
                        :class="message.user_id === currentUserId ? 'items-end' : 'items-start'">
                        <p class="text-xs text-slate-500" x-text="message.user_name"></p>
                        <div class="relative">
                            <div class="px-4 py-2.5 rounded-2xl text-sm wrap-break-word"
                                :class="message.user_id === currentUserId ? 'bg-brand-500 text-white rounded-tr-sm' : 'bg-surface-100 text-slate-200 rounded-tl-sm'">
                                <template x-if="message.is_file && message.file_url">
                                    <div>
                                        <a :href="message.file_url" target="_blank" rel="noopener"
                                            class="underline underline-offset-2"
                                            :class="message.user_id === currentUserId ? 'text-white' : 'text-brand-400'">
                                            📎 File attachment
                                        </a>
                                        <p x-show="message.content" class="mt-1" x-text="message.content"></p>
                                    </div>
                                </template>
                                <template x-if="!message.is_file">
                                    <span x-text="message.content"></span>
                                </template>
                            </div>
                            <a :href="threadUrl(message.id)"
                                class="text-xs text-brand-400 hover:text-brand-300 mt-0.5 block">
                                <span x-text="message.reply_count > 0 ? message.reply_count + ' ' + (message.reply_count === 1 ? 'reply' : 'replies') + ' →' : 'Open thread →'"></span>
                            </a>
                        </div>
                        <span class="text-xs text-slate-600" x-text="message.created_at"></span>
                    </div>
                </div>
            </template>
        </div>

        <div class="pt-4 border-t border-white/5 mt-4 shrink-0">
            <template x-if="canSend">
                <form @submit.prevent="sendMessage" enctype="multipart/form-data"
                    class="flex flex-col sm:flex-row gap-3">
                    <input type="text" x-model="draft" autocomplete="off" placeholder="Write a message..."
                        :disabled="sending"
                        class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500 disabled:opacity-50">
                    <input type="file" x-ref="fileInput" accept="*/*" @change="file = $refs.fileInput.files[0]"
                        class="text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-white/5 file:text-slate-300 hover:file:bg-white/10">
                    <button type="submit" :disabled="sending || (!draft.trim() && !file)"
                        class="bg-brand-500 hover:bg-brand-600 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
                        <span x-text="sending ? 'Sending…' : 'Send'"></span>
                    </button>
                </form>
            </template>
            <template x-if="!canSend">
                <p class="text-sm text-slate-500 text-center">You don't have permission to send messages here.</p>
            </template>
        </div>
    </div>

    @push('scripts')
        <script>
            function chatPanel(config) {
                return {
                    messages: config.messages ?? [],
                    pollUrl: config.pollUrl,
                    storeUrl: config.storeUrl,
                    threadUrlTemplate: config.threadUrlTemplate,
                    currentUserId: config.currentUserId,
                    canSend: config.canSend,
                    draft: '',
                    file: null,
                    sending: false,
                    pollTimer: null,

                    init() {
                        this.scrollToBottom();
                        this.pollTimer = setInterval(() => this.poll(), 3000);
                    },

                    destroy() {
                        if (this.pollTimer) clearInterval(this.pollTimer);
                    },

                    lastId() {
                        if (!this.messages.length) return 0;
                        return Math.max(...this.messages.map(m => m.id));
                    },

                    threadUrl(messageId) {
                        return this.threadUrlTemplate.replace('__ID__', messageId);
                    },

                    scrollToBottom() {
                        this.$nextTick(() => {
                            const el = this.$refs.messagesContainer;
                            if (el) el.scrollTop = el.scrollHeight;
                        });
                    },

                    appendMessages(incoming) {
                        const existing = new Set(this.messages.map(m => m.id));
                        let added = false;

                        for (const message of incoming) {
                            if (!existing.has(message.id)) {
                                this.messages.push(message);
                                added = true;
                            }
                        }

                        if (added) this.scrollToBottom();
                    },

                    async poll() {
                        try {
                            const response = await fetch(`${this.pollUrl}?after=${this.lastId()}`, {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });

                            if (!response.ok) return;

                            const data = await response.json();
                            this.appendMessages(data.messages ?? []);
                        } catch (error) {
                            // Ignore transient network errors during polling.
                        }
                    },

                    async sendMessage() {
                        if (this.sending) return;
                        if (!this.draft.trim() && !this.file) return;

                        this.sending = true;
                        const formData = new FormData();

                        if (this.draft.trim()) {
                            formData.append('content', this.draft.trim());
                        }

                        if (this.file) {
                            formData.append('file', this.file);
                        }

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
                            this.appendMessages([data.message]);
                            this.draft = '';
                            this.file = null;
                            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                        } catch (error) {
                            // Keep draft on failure so the user can retry.
                        } finally {
                            this.sending = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endsection
