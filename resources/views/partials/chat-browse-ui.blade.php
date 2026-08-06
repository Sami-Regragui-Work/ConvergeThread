{{-- Chat search + media (scoped pages only). Proton-style local decrypt index. --}}
@php
    $browseChatType = request()->route('chatType');
    $browseChatId = request()->route('chatId') ? (int) request()->route('chatId') : null;
    if (!$browseChatType && request()->routeIs('groups.show') && request()->route('group')) {
        $browseChatType = 'group';
        $browseChatId = (int) request()->route('group')->id;
    }
    if (!$browseChatType && request()->routeIs('merge-sessions.show') && request()->route('mergeSession')) {
        $browseChatType = 'merge';
        $browseChatId = (int) request()->route('mergeSession')->id;
    }
@endphp
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('chatBrowse', {
            chatsUrl: @js(route('messages.chats')),
            cryptoPublicKeyUrl: @js(route('messages.crypto.public-key')),
            currentUserId: @js(auth()->id()),
            chats: [],
            participants: [],
            showSearch: false,
            showMedia: false,
            selectedChatKey: @js($browseChatType && $browseChatId ? $browseChatType.':'.$browseChatId : ''),
            searchScope: 'one',
            query: '',
            authorQuery: '',
            filterUserId: null,
            filterAttachments: '',
            filterFrom: '',
            filterTo: '',
            showAuthorMenu: false,
            results: [],
            mediaSections: [],
            busy: false,
            status: '',
            mediaStatus: '',
            ready: false,

            async boot() {
                if (this.ready) return;
                this.ready = true;
                await this.loadChats();
                if (this.selectedChatKey && this.selectedChatKey !== '__all__') {
                    await this.loadParticipants();
                }
            },

            async loadChats() {
                const preset = this.selectedChatKey;
                try {
                    const res = await fetch(this.chatsUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (res.ok) {
                        const data = await res.json();
                        this.chats = data.chats || [];
                    }
                } catch (e) {
                    this.chats = [];
                }
                // Native <select> + Alpine x-for options often miss the preset value until rebound.
                await this.rebindSelectedChat(preset);
            },

            async rebindSelectedChat(preset) {
                const key = preset === undefined ? this.selectedChatKey : preset;
                this.selectedChatKey = key || '';
                await Promise.resolve();
                this.paintChatSelects();
            },

            paintChatSelects() {
                const key = this.selectedChatKey || '';
                const options = ['<option value="">Select a chat…</option>'];
                if (this.showSearch) {
                    options.push('<option value="__all__">All my chats</option>');
                }
                for (const chat of this.chats || []) {
                    const value = chat.type + ':' + chat.id;
                    const label = this.escapeHtml(chat.name + ' (' + chat.kind + ')');
                    options.push('<option value="' + value + '"' + (value === key ? ' selected' : '') + '>' + label + '</option>');
                }
                const html = options.join('');
                document.querySelectorAll('[data-chat-browse-select]').forEach((el) => {
                    if (!(el instanceof HTMLSelectElement)) return;
                    const keepAll = el.dataset.allowAll === '1';
                    if (keepAll) {
                        el.innerHTML = html;
                    } else {
                        el.innerHTML = options.filter((o) => !o.includes('value="__all__"')).join('');
                    }
                    el.value = key;
                });
            },

            escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            },

            parseChatKey() {
                if (!this.selectedChatKey || this.selectedChatKey === '__all__') return null;
                const [type, id] = this.selectedChatKey.split(':');
                if (!type || !id) return null;
                return { type, id: Number(id) };
            },

            isAllChats() {
                return this.selectedChatKey === '__all__';
            },

            async openSearch() {
                this.showSearch = true;
                this.showMedia = false;
                await this.boot();
                await this.rebindSelectedChat();
                if (this.isAllChats()) {
                    await this.onChatPicked();
                } else if (this.selectedChatKey) {
                    await this.loadParticipants();
                    await this.syncSelected(false);
                }
            },

            async openMedia() {
                this.showMedia = true;
                this.showSearch = false;
                this.mediaStatus = '';
                try {
                    await this.boot();
                    await this.rebindSelectedChat();
                    // Ensure selects are painted after the modal is shown.
                    await Promise.resolve();
                    this.paintChatSelects();
                    if (this.selectedChatKey && !this.isAllChats()) {
                        await this.loadMedia();
                    } else {
                        this.mediaStatus = 'Select a chat to browse files.';
                    }
                } catch (e) {
                    console.error(e);
                    this.mediaStatus = 'Could not open files browser.';
                }
            },

            async onChatPicked() {
                this.results = [];
                this.status = '';
                this.filterUserId = null;
                this.authorQuery = '';
                this.participants = [];
                if (this.isAllChats()) {
                    this.busy = true;
                    this.status = 'Indexing all chats…';
                    try {
                        const result = await window.ChatSearchIndex.syncChats(this.chats, {
                            userId: this.currentUserId,
                            cryptoPublicKeyUrl: this.cryptoPublicKeyUrl,
                            force: false,
                        });
                        this.status = 'Indexed ' + result.imported + ' message(s) across chats';
                    } catch (e) {
                        this.status = 'Could not index all chats.';
                    } finally {
                        this.busy = false;
                    }
                    return;
                }
                if (this.selectedChatKey) {
                    await this.loadParticipants();
                    await this.syncSelected(false);
                }
            },

            async loadParticipants() {
                const chat = this.parseChatKey();
                if (!chat) {
                    this.participants = [];
                    return;
                }
                try {
                    const res = await fetch('/messages/' + chat.type + '/' + chat.id + '/participants', {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.participants = data.participants || [];
                } catch (e) {
                    this.participants = [];
                }
            },

            filteredAuthors() {
                const q = (this.authorQuery || '').toLowerCase().trim();
                return (this.participants || []).filter((p) => {
                    if (!q) return true;
                    return (p.username || '').toLowerCase().includes(q)
                        || (p.display_name || '').toLowerCase().includes(q)
                        || (p.label || '').toLowerCase().includes(q);
                }).slice(0, 8);
            },

            pickAuthor(person) {
                this.filterUserId = person.id;
                this.authorQuery = person.username || person.label || '';
                this.showAuthorMenu = false;
            },

            clearAuthor() {
                this.filterUserId = null;
                this.authorQuery = '';
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
                if (!this.selectedChatKey) {
                    this.status = 'Select a chat first.';
                    return;
                }
                if (!window.ChatSearchIndex) return;
                this.busy = true;
                try {
                    if (this.isAllChats()) {
                        await window.ChatSearchIndex.syncChats(this.chats, {
                            userId: this.currentUserId,
                            cryptoPublicKeyUrl: this.cryptoPublicKeyUrl,
                            force: false,
                        });
                    } else {
                        await this.syncSelected(false);
                    }
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

                    const filters = {
                        query: this.query,
                        userId: this.filterUserId ? Number(this.filterUserId) : null,
                        hasAttachments,
                        fromDate,
                        toDate,
                        limit: 80,
                        viewerUserId: this.currentUserId,
                    };

                    if (this.isAllChats()) {
                        this.results = await window.ChatSearchIndex.searchAll(filters);
                    } else {
                        const chat = this.parseChatKey();
                        this.results = await window.ChatSearchIndex.search({
                            ...filters,
                            chatType: chat.type,
                            chatId: chat.id,
                        });
                    }
                    this.status = this.results.length + ' result(s)';
                } catch (e) {
                    this.status = 'Search failed.';
                } finally {
                    this.busy = false;
                }
            },

            async loadMedia() {
                const chat = this.parseChatKey();
                if (!chat) {
                    this.mediaStatus = 'Select a chat first.';
                    return;
                }
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

                    this.mediaSections = [...sections];
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
                try { return new Date(iso).toLocaleString(); } catch (e) { return iso; }
            },

            goToMessage(hit) {
                const chat = this.parseChatKey() || {
                    type: hit.chatType,
                    id: hit.chatId,
                };
                if (!chat?.type || !chat?.id) return;
                if (hit.parentId) {
                    window.location.href = '/messages/' + hit.parentId + '/thread?message=' + hit.messageId;
                } else {
                    window.location.href = '/messages/' + chat.type + '/' + chat.id + '?message=' + hit.messageId;
                }
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
        });
    });

    function getChatBrowseStore() {
        try {
            if (typeof Alpine === 'undefined') return null;
            return Alpine.store('chatBrowse');
        } catch (e) {
            return null;
        }
    }

    window.__openChatSearch = function () {
        window.dispatchEvent(new CustomEvent('ct-open-search'));
        const store = getChatBrowseStore();
        if (store?.openSearch) store.openSearch();
    };
    window.__openChatMedia = function () {
        window.dispatchEvent(new CustomEvent('ct-open-media'));
        const store = getChatBrowseStore();
        if (store?.openMedia) {
            store.openMedia();
            return;
        }
        // Alpine may not have booted yet — retry briefly.
        let tries = 0;
        const timer = setInterval(() => {
            const s = getChatBrowseStore();
            if (s?.openMedia) {
                clearInterval(timer);
                s.openMedia();
            } else if (++tries > 20) {
                clearInterval(timer);
                console.warn('Chat files browser is not ready yet.');
            }
        }, 50);
    };
</script>

<div
    x-data="{
        init() {
            window.addEventListener('ct-open-search', () => Alpine.store('chatBrowse')?.openSearch?.());
            window.addEventListener('ct-open-media', () => Alpine.store('chatBrowse')?.openMedia?.());
        }
    }"
    class="contents"
>
    {{-- Alpine x-teleport only mounts firstElementChild — both modals must share one root. --}}
    <template x-teleport="body">
        <div>
        <div x-show="$store.chatBrowse.showSearch" x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[200] flex items-start justify-center p-4 sm:p-8"
            @keydown.escape.window="if ($store.chatBrowse.showSearch) $store.chatBrowse.showSearch = false">
            <div class="absolute inset-0 bg-black/70" @click="$store.chatBrowse.showSearch = false"></div>
            <div class="relative w-full max-w-2xl max-h-[85vh] flex flex-col rounded-2xl border border-white/10 bg-surface-300 shadow-2xl overflow-hidden"
                @click.outside="$store.chatBrowse.showAuthorMenu = false">
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/5">
                    <div>
                        <p class="text-sm font-semibold text-white">Search chats</p>
                        <p class="text-[11px] text-slate-500">Pick a chat, then search body keywords locally (E2EE-safe)</p>
                    </div>
                    <button type="button" @click="$store.chatBrowse.showSearch = false" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div class="p-4 space-y-3 border-b border-white/5">
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">Chat</label>
                        <select data-chat-browse-select data-allow-all="1"
                            @change="$store.chatBrowse.selectedChatKey = $event.target.value; $store.chatBrowse.onChatPicked()"
                            class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2">
                            <option value="">Select a chat…</option>
                            <option value="__all__">All my chats</option>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">All chats only searches chats you belong to (indexed locally).</p>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">Keywords</label>
                        <input type="search" x-model="$store.chatBrowse.query"
                            @keydown.enter.prevent="$store.chatBrowse.runSearch()"
                            :disabled="!$store.chatBrowse.selectedChatKey"
                            placeholder="Search message body…"
                            class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2 disabled:opacity-40">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="relative">
                            <label class="block text-[11px] text-slate-500 mb-1">Author</label>
                            <div class="flex gap-1">
                                <input type="text" x-model="$store.chatBrowse.authorQuery"
                                    @focus="$store.chatBrowse.showAuthorMenu = true"
                                    @input="$store.chatBrowse.showAuthorMenu = true; $store.chatBrowse.filterUserId = null"
                                    :disabled="!$store.chatBrowse.selectedChatKey"
                                    placeholder="@username"
                                    class="flex-1 bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2 disabled:opacity-40">
                                <button type="button" x-show="$store.chatBrowse.filterUserId" x-cloak
                                    @click="$store.chatBrowse.clearAuthor()"
                                    class="px-2 rounded-lg border border-white/10 text-slate-400 text-xs">Clear</button>
                            </div>
                            <div x-show="$store.chatBrowse.showAuthorMenu && $store.chatBrowse.selectedChatKey && $store.chatBrowse.filteredAuthors().length"
                                x-cloak
                                class="absolute z-10 mt-1 w-full rounded-xl border border-white/10 bg-surface-200 shadow-xl max-h-40 overflow-y-auto">
                                <template x-for="person in $store.chatBrowse.filteredAuthors()" :key="person.id">
                                    <button type="button" @click="$store.chatBrowse.pickAuthor(person)"
                                        class="w-full text-left px-3 py-2 text-sm hover:bg-white/5 flex items-center gap-2">
                                        <span class="mention-pill text-xs" x-text="'@' + (person.username || person.label)"></span>
                                        <span class="text-xs text-slate-500 truncate" x-text="person.display_name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Attachments</label>
                            <select x-model="$store.chatBrowse.filterAttachments"
                                :disabled="!$store.chatBrowse.selectedChatKey"
                                class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2 disabled:opacity-40">
                                <option value="">Any</option>
                                <option value="1">Has files</option>
                                <option value="0">No files</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">From date</label>
                            <input type="date" x-model="$store.chatBrowse.filterFrom"
                                :disabled="!$store.chatBrowse.selectedChatKey"
                                class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2 disabled:opacity-40">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">To date</label>
                            <input type="date" x-model="$store.chatBrowse.filterTo"
                                :disabled="!$store.chatBrowse.selectedChatKey"
                                class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2 disabled:opacity-40">
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="$store.chatBrowse.runSearch()"
                            :disabled="$store.chatBrowse.busy || !$store.chatBrowse.selectedChatKey"
                            class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-xs font-medium disabled:opacity-40">
                            <span x-text="$store.chatBrowse.busy ? 'Indexing…' : 'Search'"></span>
                        </button>
                        <button type="button" @click="$store.chatBrowse.syncSelected(true)"
                            :disabled="$store.chatBrowse.busy || !$store.chatBrowse.selectedChatKey"
                            class="px-3 py-1.5 rounded-lg border border-white/10 text-slate-300 text-xs hover:bg-white/5 disabled:opacity-40">
                            Re-index chat
                        </button>
                        <span class="text-[11px] text-slate-500" x-text="$store.chatBrowse.status"></span>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <template x-if="!$store.chatBrowse.results.length && !$store.chatBrowse.busy">
                        <p class="text-sm text-slate-500 text-center py-8">No results yet.</p>
                    </template>
                    <template x-for="hit in $store.chatBrowse.results" :key="hit.messageId">
                        <button type="button" @click="$store.chatBrowse.goToMessage(hit)"
                            class="w-full text-left rounded-xl border border-white/5 bg-surface-200/50 hover:bg-white/5 px-3 py-2.5 transition">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-medium text-brand-300" x-text="hit.userName"></p>
                                <p class="text-[10px] text-slate-500" x-text="$store.chatBrowse.formatWhen(hit.createdAt)"></p>
                            </div>
                            <p class="text-sm text-slate-200 mt-1 line-clamp-2" x-text="hit.body || '(no text)'"></p>
                            <p x-show="hit.attachmentNames?.length" class="text-[11px] text-slate-500 mt-1"
                                x-text="'Files: ' + (hit.attachmentNames || []).join(', ')"></p>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div x-show="$store.chatBrowse.showMedia" x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[200] flex items-start justify-center p-4 sm:p-8"
            @keydown.escape.window="if ($store.chatBrowse.showMedia) $store.chatBrowse.showMedia = false">
            <div class="absolute inset-0 bg-black/70" @click="$store.chatBrowse.showMedia = false"></div>
            <div class="relative w-full max-w-3xl max-h-[85vh] flex flex-col rounded-2xl border border-white/10 bg-surface-300 shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/5">
                    <div>
                        <p class="text-sm font-semibold text-white">Files by thread</p>
                        <p class="text-[11px] text-slate-500">Grouped by root message · jump to place in chat</p>
                    </div>
                    <button type="button" @click="$store.chatBrowse.showMedia = false" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div class="p-4 border-b border-white/5 space-y-2">
                    <label class="block text-[11px] text-slate-500">Chat</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select data-chat-browse-select
                            @change="$store.chatBrowse.selectedChatKey = $event.target.value; $store.chatBrowse.loadMedia()"
                            class="flex-1 bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2">
                            <option value="">Select a chat…</option>
                        </select>
                        <button type="button" @click="$store.chatBrowse.loadMedia()"
                            :disabled="$store.chatBrowse.busy || !$store.chatBrowse.selectedChatKey"
                            class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-xs font-medium disabled:opacity-40">
                            Refresh
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-6">
                    <p x-show="$store.chatBrowse.mediaStatus" class="text-xs text-slate-500" x-text="$store.chatBrowse.mediaStatus"></p>
                    <template x-if="!$store.chatBrowse.mediaSections.length && !$store.chatBrowse.busy">
                        <p class="text-sm text-slate-500 text-center py-8">No files in this chat.</p>
                    </template>
                    <template x-for="section in $store.chatBrowse.mediaSections" :key="section.root_id">
                        <section class="space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Thread</p>
                                    <p class="text-sm text-white truncate" x-text="section.root_label || ('#' + section.root_id)"></p>
                                    <p class="text-[11px] text-slate-500"
                                        x-text="(section.root_user_name || '') + (section.root_created_at ? ' · ' + $store.chatBrowse.formatWhen(section.root_created_at) : '')"></p>
                                </div>
                                <button type="button" @click="$store.chatBrowse.goToRoot(section)"
                                    class="shrink-0 text-[11px] px-2 py-1 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5">
                                    Open thread
                                </button>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <template x-for="file in section.files" :key="(file.id || file.url) + '-' + file.message_id">
                                    <div class="rounded-xl border border-white/10 bg-surface-200/40 overflow-hidden flex flex-col">
                                        <div class="aspect-video bg-black flex items-center justify-center relative">
                                            {{-- x-show only: nested x-if inside x-for breaks Alpine and killed the Files modal --}}
                                            <img x-show="file.local_preview && file.is_image" x-cloak
                                                :src="file.local_preview" class="absolute inset-0 w-full h-full object-cover" alt="">
                                            <video x-show="file.local_preview && file.is_video" x-cloak
                                                :src="file.local_preview" class="absolute inset-0 w-full h-full object-cover" muted></video>
                                            <span x-show="!file.local_preview" class="text-[10px] text-slate-400 uppercase" x-text="file.ext || file.kind || 'file'"></span>
                                        </div>
                                        <div class="p-2 space-y-1.5">
                                            <p class="text-[11px] text-slate-200 truncate" :title="file.name" x-text="file.name"></p>
                                            <div class="flex gap-1">
                                                <a :href="file.local_preview || file.url" target="_blank" rel="noopener"
                                                    class="flex-1 text-center text-[10px] px-1.5 py-1 rounded border border-white/10 text-slate-300 hover:bg-white/5">Open</a>
                                                <button type="button" @click="$store.chatBrowse.goToFile(file)"
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
        </div>
    </template>
</div>
