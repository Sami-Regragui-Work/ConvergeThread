{{-- Global chat search + media browse (header). Proton-style: body search via local decrypted index. --}}
@auth
    @unless(auth()->user()->isOwner())
        <div x-data="chatBrowse({
            chatsUrl: @js(route('messages.chats')),
            cryptoPublicKeyUrl: @js(route('messages.crypto.public-key')),
            currentUserId: @js(auth()->id()),
            currentChatType: @js(request()->route('chatType')),
            currentChatId: @js(request()->route('chatId') ? (int) request()->route('chatId') : null),
        })" x-cloak>
            <template x-teleport="body">
                {{-- Search modal --}}
                <div x-show="showSearch" class="fixed inset-0 z-200 flex items-start justify-center p-4 sm:p-8" @keydown.escape.window="showSearch = false">
                    <div class="absolute inset-0 bg-black/70" @click="showSearch = false"></div>
                    <div class="relative w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl border border-white/10 bg-surface-300 shadow-2xl overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/5">
                            <div>
                                <p class="text-sm font-semibold text-white">Search chats</p>
                                <p class="text-[11px] text-slate-500">Body keywords search locally after decrypt (E2EE-safe)</p>
                            </div>
                            <button type="button" @click="showSearch = false" class="text-slate-400 hover:text-white text-sm">Close</button>
                        </div>
                        <div class="p-4 space-y-3 border-b border-white/5">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <select x-model="selectedChatKey" @change="onChatPicked()"
                                    class="bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2">
                                    <option value="">Select a chat…</option>
                                    <template x-for="chat in chats" :key="chat.type + ':' + chat.id">
                                        <option :value="chat.type + ':' + chat.id" x-text="chat.name + ' (' + chat.kind + ')'"></option>
                                    </template>
                                </select>
                                <input type="search" x-model="query" @keydown.enter.prevent="runSearch()"
                                    placeholder="Keywords in message body…"
                                    class="bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <input type="number" x-model="filterUserId" placeholder="Author user id (optional)"
                                    class="bg-surface-200 border border-white/10 text-white text-xs rounded-lg px-3 py-2">
                                <select x-model="filterAttachments"
                                    class="bg-surface-200 border border-white/10 text-white text-xs rounded-lg px-3 py-2">
                                    <option value="">Any attachments</option>
                                    <option value="1">Has files</option>
                                    <option value="0">No files</option>
                                </select>
                                <div class="flex gap-2">
                                    <input type="date" x-model="filterFrom"
                                        class="flex-1 bg-surface-200 border border-white/10 text-white text-xs rounded-lg px-2 py-2">
                                    <input type="date" x-model="filterTo"
                                        class="flex-1 bg-surface-200 border border-white/10 text-white text-xs rounded-lg px-2 py-2">
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="runSearch()" :disabled="busy || !selectedChatKey"
                                    class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-xs font-medium disabled:opacity-40">
                                    <span x-text="busy ? 'Indexing…' : 'Search'"></span>
                                </button>
                                <button type="button" @click="syncSelected(true)" :disabled="busy || !selectedChatKey"
                                    class="px-3 py-1.5 rounded-lg border border-white/10 text-slate-300 text-xs hover:bg-white/5 disabled:opacity-40">
                                    Re-index chat
                                </button>
                                <span class="text-[11px] text-slate-500" x-text="status"></span>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto p-3 space-y-2">
                            <template x-if="!results.length && !busy">
                                <p class="text-sm text-slate-500 text-center py-8">No results yet.</p>
                            </template>
                            <template x-for="hit in results" :key="hit.messageId">
                                <button type="button" @click="goToMessage(hit)"
                                    class="w-full text-left rounded-xl border border-white/5 bg-surface-200/50 hover:bg-white/5 px-3 py-2.5 transition">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-medium text-brand-300" x-text="hit.userName"></p>
                                        <p class="text-[10px] text-slate-500" x-text="formatWhen(hit.createdAt)"></p>
                                    </div>
                                    <p class="text-sm text-slate-200 mt-1 line-clamp-2" x-text="hit.body || '(no text)'"></p>
                                    <p x-show="hit.attachmentNames?.length" class="text-[11px] text-slate-500 mt-1"
                                        x-text="'Files: ' + (hit.attachmentNames || []).join(', ')"></p>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Media modal --}}
                <div x-show="showMedia" class="fixed inset-0 z-200 flex items-start justify-center p-4 sm:p-8" @keydown.escape.window="showMedia = false">
                    <div class="absolute inset-0 bg-black/70" @click="showMedia = false"></div>
                    <div class="relative w-full max-w-3xl max-h-[85vh] flex flex-col rounded-2xl border border-white/10 bg-surface-300 shadow-2xl overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/5">
                            <div>
                                <p class="text-sm font-semibold text-white">Files by thread</p>
                                <p class="text-[11px] text-slate-500">Grouped by root message · jump to place in chat</p>
                            </div>
                            <button type="button" @click="showMedia = false" class="text-slate-400 hover:text-white text-sm">Close</button>
                        </div>
                        <div class="p-4 border-b border-white/5 flex flex-col sm:flex-row gap-2">
                            <select x-model="selectedChatKey" @change="loadMedia()"
                                class="flex-1 bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2">
                                <option value="">Select a chat…</option>
                                <template x-for="chat in chats" :key="'m-' + chat.type + ':' + chat.id">
                                    <option :value="chat.type + ':' + chat.id" x-text="chat.name + ' (' + chat.kind + ')'"></option>
                                </template>
                            </select>
                            <button type="button" @click="loadMedia()" :disabled="busy || !selectedChatKey"
                                class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-xs font-medium disabled:opacity-40">
                                Refresh
                            </button>
                        </div>
                        <div class="flex-1 overflow-y-auto p-4 space-y-6">
                            <p x-show="mediaStatus" class="text-xs text-slate-500" x-text="mediaStatus"></p>
                            <template x-if="!mediaSections.length && !busy">
                                <p class="text-sm text-slate-500 text-center py-8">No files in this chat.</p>
                            </template>
                            <template x-for="section in mediaSections" :key="section.root_id">
                                <section class="space-y-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-xs uppercase tracking-wide text-slate-500">Thread</p>
                                            <p class="text-sm text-white truncate" x-text="section.root_label || ('#' + section.root_id)"></p>
                                            <p class="text-[11px] text-slate-500" x-text="(section.root_user_name || '') + (section.root_created_at ? ' · ' + formatWhen(section.root_created_at) : '')"></p>
                                        </div>
                                        <button type="button" @click="goToRoot(section)"
                                            class="shrink-0 text-[11px] px-2 py-1 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5">
                                            Open thread
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        <template x-for="file in section.files" :key="(file.id || file.url) + '-' + file.message_id">
                                            <div class="rounded-xl border border-white/10 bg-surface-200/40 overflow-hidden flex flex-col">
                                                <div class="aspect-video bg-black/40 flex items-center justify-center relative">
                                                    <template x-if="file.local_preview && file.is_image">
                                                        <img :src="file.local_preview" class="absolute inset-0 w-full h-full object-cover" alt="">
                                                    </template>
                                                    <template x-if="file.local_preview && file.is_video">
                                                        <video :src="file.local_preview" class="absolute inset-0 w-full h-full object-cover" muted></video>
                                                    </template>
                                                    <span x-show="!file.local_preview" class="text-[10px] text-slate-400 uppercase" x-text="file.ext || file.kind || 'file'"></span>
                                                </div>
                                                <div class="p-2 space-y-1.5">
                                                    <p class="text-[11px] text-slate-200 truncate" :title="file.name" x-text="file.name"></p>
                                                    <div class="flex gap-1">
                                                        <a :href="file.local_preview || file.url" target="_blank" rel="noopener"
                                                            class="flex-1 text-center text-[10px] px-1.5 py-1 rounded border border-white/10 text-slate-300 hover:bg-white/5">Open</a>
                                                        <button type="button" @click="goToFile(file)"
                                                            class="flex-1 text-[10px] px-1.5 py-1 rounded border border-brand-500/30 text-brand-300 hover:bg-brand-500/10">
                                                            Go to chat
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </section>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <script>
            window.__openChatSearch = function () {
                window.dispatchEvent(new CustomEvent('ct-open-search'));
            };
            window.__openChatMedia = function () {
                window.dispatchEvent(new CustomEvent('ct-open-media'));
            };

            function chatBrowse(config) {
                return {
                    chatsUrl: config.chatsUrl,
                    cryptoPublicKeyUrl: config.cryptoPublicKeyUrl,
                    currentUserId: config.currentUserId,
                    chats: [],
                    showSearch: false,
                    showMedia: false,
                    selectedChatKey: config.currentChatType && config.currentChatId
                        ? (config.currentChatType + ':' + config.currentChatId)
                        : '',
                    query: '',
                    filterUserId: '',
                    filterAttachments: '',
                    filterFrom: '',
                    filterTo: '',
                    results: [],
                    mediaSections: [],
                    busy: false,
                    status: '',
                    mediaStatus: '',

                    async init() {
                        window.addEventListener('ct-open-search', () => this.openSearch());
                        window.addEventListener('ct-open-media', () => this.openMedia());
                        await this.loadChats();
                    },

                    async loadChats() {
                        try {
                            const res = await fetch(this.chatsUrl, {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });
                            if (!res.ok) return;
                            const data = await res.json();
                            this.chats = data.chats || [];
                        } catch (e) {}
                    },

                    parseChatKey() {
                        if (!this.selectedChatKey) return null;
                        const [type, id] = this.selectedChatKey.split(':');
                        return { type, id: Number(id) };
                    },

                    async openSearch() {
                        this.showSearch = true;
                        this.showMedia = false;
                        if (!this.chats.length) await this.loadChats();
                        if (this.selectedChatKey) await this.syncSelected(false);
                    },

                    async openMedia() {
                        this.showMedia = true;
                        this.showSearch = false;
                        if (!this.chats.length) await this.loadChats();
                        if (this.selectedChatKey) await this.loadMedia();
                    },

                    async onChatPicked() {
                        this.results = [];
                        this.status = '';
                        if (this.selectedChatKey) await this.syncSelected(false);
                    },

                    async syncSelected(force) {
                        const chat = this.parseChatKey();
                        if (!chat || !window.ChatSearchIndex) return;
                        this.busy = true;
                        this.status = force ? 'Re-indexing…' : 'Updating local search index…';
                        try {
                            const result = await window.ChatSearchIndex.syncChat(chat.type, chat.id, {
                                userId: this.currentUserId,
                                cryptoPublicKeyUrl: this.cryptoPublicKeyUrl,
                                force,
                            });
                            this.status = 'Indexed ' + result.imported + ' message(s)';
                        } catch (e) {
                            this.status = 'Could not index this chat (need room key / membership).';
                        } finally {
                            this.busy = false;
                        }
                    },

                    async runSearch() {
                        const chat = this.parseChatKey();
                        if (!chat || !window.ChatSearchIndex) return;
                        this.busy = true;
                        try {
                            await this.syncSelected(false);
                            const fromDate = this.filterFrom ? new Date(this.filterFrom).toISOString() : null;
                            let toDate = null;
                            if (this.filterTo) {
                                const d = new Date(this.filterTo);
                                d.setHours(23, 59, 59, 999);
                                toDate = d.toISOString();
                            }
                            let hasAttachments = null;
                            if (this.filterAttachments === '1') hasAttachments = true;
                            if (this.filterAttachments === '0') hasAttachments = false;

                            this.results = await window.ChatSearchIndex.search({
                                chatType: chat.type,
                                chatId: chat.id,
                                query: this.query,
                                userId: this.filterUserId ? Number(this.filterUserId) : null,
                                hasAttachments,
                                fromDate,
                                toDate,
                                limit: 80,
                            });
                            this.status = this.results.length + ' result(s)';
                        } catch (e) {
                            this.status = 'Search failed.';
                        } finally {
                            this.busy = false;
                        }
                    },

                    async loadMedia() {
                        const chat = this.parseChatKey();
                        if (!chat) return;
                        this.busy = true;
                        this.mediaStatus = 'Loading files…';
                        try {
                            const res = await fetch('/messages/' + chat.type + '/' + chat.id + '/media', {
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });
                            if (!res.ok) throw new Error('media');
                            const data = await res.json();
                            let sections = data.sections || [];

                            let roomKey = null;
                            if (window.ChatSearchIndex?.resolveRoomKey) {
                                try {
                                    roomKey = await window.ChatSearchIndex.resolveRoomKey(
                                        chat.type,
                                        chat.id,
                                        this.currentUserId,
                                        this.cryptoPublicKeyUrl,
                                    );
                                } catch (e) {
                                    roomKey = null;
                                }
                            }

                            for (const section of sections) {
                                if (section.root_is_encrypted && section.root_content && roomKey && window.ChatCrypto) {
                                    try {
                                        section.root_label = await window.ChatCrypto.decryptText(roomKey, section.root_content);
                                    } catch (e) {
                                        section.root_label = 'Encrypted thread';
                                    }
                                } else {
                                    section.root_label = section.root_preview || ('Thread #' + section.root_id);
                                }

                                for (const file of section.files) {
                                    file.local_preview = null;
                                    if (!file.is_encrypted || !file.encryption_iv || !roomKey || !window.ChatCrypto) {
                                        file.local_preview = file.preview_url || null;
                                        continue;
                                    }
                                    try {
                                        const bin = await fetch(file.url, { credentials: 'same-origin' });
                                        if (!bin.ok) continue;
                                        const buf = await bin.arrayBuffer();
                                        const plain = await window.ChatCrypto.decryptBytes(roomKey, file.encryption_iv, buf);
                                        const blob = new Blob([plain], { type: file.mime_type || 'application/octet-stream' });
                                        file.local_preview = URL.createObjectURL(blob);
                                    } catch (e) {}
                                }
                            }

                            this.mediaSections = sections;
                            this.mediaStatus = sections.length ? '' : 'No files in this chat.';
                        } catch (e) {
                            this.mediaSections = [];
                            this.mediaStatus = 'Could not load media.';
                        } finally {
                            this.busy = false;
                        }
                    },

                    formatWhen(iso) {
                        if (!iso) return '';
                        try {
                            return new Date(iso).toLocaleString();
                        } catch (e) {
                            return iso;
                        }
                    },

                    goToMessage(hit) {
                        const chat = this.parseChatKey();
                        if (!chat) return;
                        let url;
                        if (hit.parentId) {
                            url = '/messages/' + hit.parentId + '/thread?message=' + hit.messageId;
                        } else {
                            url = '/messages/' + chat.type + '/' + chat.id + '?message=' + hit.messageId;
                        }
                        window.location.href = url;
                    },

                    goToRoot(section) {
                        const chat = this.parseChatKey();
                        if (!chat) return;
                        window.location.href = '/messages/' + chat.type + '/' + chat.id + '?message=' + section.root_id;
                    },

                    goToFile(file) {
                        const chat = this.parseChatKey();
                        if (!chat) return;
                        if (file.parent_id) {
                            window.location.href = '/messages/' + file.parent_id + '/thread?message=' + file.message_id;
                        } else {
                            window.location.href = '/messages/' + chat.type + '/' + chat.id + '?message=' + file.message_id;
                        }
                    },
                };
            }
        </script>
    @endunless
@endauth
