@verbatim
<script>
    function chatPanel(config) {
        return {
            messages: config.messages ?? config.replies ?? [],
            participants: config.participants ?? [],
            mentionSuggestions: config.mentionSuggestions ?? [],
            pollUrl: config.pollUrl,
            mentionsUrl: config.mentionsUrl,
            markMentionUrlTemplate: config.markMentionUrlTemplate,
            storeUrl: config.storeUrl,
            updateUrlTemplate: config.updateUrlTemplate ?? '',
            threadUrlTemplate: config.threadUrlTemplate,
            currentUserId: config.currentUserId,
            canSend: config.canSend ?? config.canReply ?? false,
            showThreadLink: config.showThreadLink ?? false,
            parentId: config.parentId ?? null,
            chatType: config.chatType ?? null,
            chatId: config.chatId ?? null,
            draft: '',
            files: [],
            filePreviews: [],
            sending: false,
            sendError: '',
            maxFileBytes: 50 * 1024 * 1024,
            pollTimer: null,
            echoBound: false,
            mentionQueue: [...(config.mentionIds ?? [])],
            mentionIndex: 0,
            showMentionMenu: false,
            showSelectedPicker: false,
            mentionFilter: '',
            activeMentionIndex: -1,
            selectedUserIds: [],
            selectedSearch: '',
            editingId: null,
            editDraft: '',
            showCallModal: false,
            callType: 'voice',

            init() {
                this.scrollToBottom();
                this.$nextTick(() => this.focusDraft());
                this.setupRealtime();
                this.pollTimer = setInterval(() => this.poll(), this.echoBound ? 15000 : 3000);
            },

            destroy() {
                if (this.pollTimer) clearInterval(this.pollTimer);
                if (this.echoBound && window.Echo && this.chatType && this.chatId) {
                    window.Echo.leave('chat.' + this.chatType + '.' + this.chatId);
                }
                this.revokeFilePreviews();
            },

            focusDraft() {
                this.$refs.draftInput?.focus();
            },

            setupRealtime() {
                if (!window.Echo || !this.chatType || !this.chatId) return;

                window.Echo.private('chat.' + this.chatType + '.' + this.chatId)
                    .listen('.message.sent', (e) => {
                        const message = e?.message;
                        if (!message) {
                            this.poll();
                            return;
                        }

                        if (this.parentId) {
                            if (Number(message.parent_id) !== Number(this.parentId)) return;
                        } else if (message.parent_id) {
                            return;
                        }

                        this.appendMessages([message]);
                    });

                this.echoBound = true;
            },

            mentionCount() {
                return this.mentionQueue.length;
            },

            filteredSuggestions() {
                const q = this.mentionFilter.toLowerCase();
                if (!q) return this.mentionSuggestions;
                return this.mentionSuggestions.filter(item =>
                    item.token.toLowerCase().includes(q) || (item.label || '').toLowerCase().includes(q)
                );
            },

            filteredForSelected() {
                const q = this.selectedSearch.toLowerCase();
                return this.participants.filter(p => {
                    if (!q) return true;
                    return (p.display_name || '').toLowerCase().includes(q)
                        || (p.username || '').toLowerCase().includes(q);
                });
            },

            participantLabel(id) {
                const person = this.participants.find(p => p.id === id);
                return person?.display_name || person?.username || ('User #' + id);
            },

            toggleMentionMenu() {
                this.showMentionMenu = !this.showMentionMenu;
                this.mentionFilter = '';
                this.activeMentionIndex = -1;
                this.$nextTick(() => this.focusDraft());
            },

            closeMentionMenu() {
                this.showMentionMenu = false;
                this.activeMentionIndex = -1;
                this.mentionFilter = '';
            },

            pickSuggestion(item) {
                if (!item) return;
                if (item.special === 'selected') {
                    this.showSelectedPicker = true;
                    this.closeMentionMenu();
                    return;
                }
                this.insertMention(item.token);
            },

            acceptActiveMention() {
                const items = this.filteredSuggestions();
                if (!items.length) return false;
                const idx = this.activeMentionIndex >= 0 ? this.activeMentionIndex : 0;
                this.pickSuggestion(items[idx]);
                return true;
            },

            mentionNav(delta) {
                if (!this.showMentionMenu) return;
                const items = this.filteredSuggestions();
                if (!items.length) return;

                if (this.activeMentionIndex < 0) {
                    this.activeMentionIndex = delta > 0 ? 0 : items.length - 1;
                } else {
                    this.activeMentionIndex = (this.activeMentionIndex + delta + items.length) % items.length;
                }
            },

            onDraftKeydown(event) {
                if (!this.showMentionMenu) return;

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    this.mentionNav(1);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.mentionNav(-1);
                    return;
                }

                if (event.key === 'Tab') {
                    if (!this.filteredSuggestions().length) return;
                    event.preventDefault();
                    this.acceptActiveMention();
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    this.closeMentionMenu();
                    return;
                }

                if (event.key === 'Enter' && this.activeMentionIndex >= 0) {
                    event.preventDefault();
                    this.acceptActiveMention();
                }
            },

            insertMention(token) {
                const fragment = /@[A-Za-z0-9_:.\/-]*$/;
                if (fragment.test(this.draft)) {
                    this.draft = this.draft.replace(fragment, token + ' ');
                } else {
                    const needsSpace = this.draft.length > 0 && !/\s$/.test(this.draft);
                    this.draft = this.draft + (needsSpace ? ' ' : '') + token + ' ';
                }
                this.closeMentionMenu();
                this.$nextTick(() => this.focusDraft());
            },

            toggleSelected(id) {
                const set = new Set(this.selectedUserIds);
                if (set.has(id)) set.delete(id); else set.add(id);
                this.selectedUserIds = [...set];
            },

            selectAllFiltered() {
                const ids = this.filteredForSelected().map(p => p.id);
                this.selectedUserIds = [...new Set([...this.selectedUserIds, ...ids])];
            },

            unselectAllFiltered() {
                const remove = new Set(this.filteredForSelected().map(p => p.id));
                this.selectedUserIds = this.selectedUserIds.filter(id => !remove.has(id));
            },

            confirmSelected() {
                if (!this.draft.toLowerCase().includes('@selected')) {
                    this.draft = (this.draft + ' @selected').trim();
                }
                this.showSelectedPicker = false;
            },

            onDraftInput() {
                const match = this.draft.match(/@([A-Za-z0-9_:.\/-]*)$/);
                if (match) {
                    this.mentionFilter = match[1];
                    this.showMentionMenu = true;
                    this.activeMentionIndex = -1;
                } else {
                    this.mentionFilter = '';
                }
            },

            lastId() {
                if (!this.messages.length) return 0;
                return Math.max(...this.messages.map(m => m.id));
            },

            threadUrl(messageId) {
                return this.threadUrlTemplate.replace('__ID__', messageId);
            },

            markUrl(messageId) {
                return this.markMentionUrlTemplate.replace('__ID__', messageId);
            },

            updateUrl(messageId) {
                return this.updateUrlTemplate.replace('__ID__', messageId);
            },

            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.messagesContainer;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            scrollToMessage(messageId) {
                this.$nextTick(() => {
                    const el = this.$refs.messagesContainer?.querySelector(`[data-message-id="${messageId}"]`);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.classList.add('ring-2', 'ring-brand-400/60', 'rounded-xl');
                        setTimeout(() => el.classList.remove('ring-2', 'ring-brand-400/60', 'rounded-xl'), 2200);
                    }
                });
            },

            async jumpNextMention() {
                if (!this.mentionQueue.length) return;

                if (this.mentionIndex >= this.mentionQueue.length) {
                    this.mentionIndex = 0;
                }

                const messageId = this.mentionQueue[this.mentionIndex];
                this.scrollToMessage(messageId);

                try {
                    await fetch(this.markUrl(messageId), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                    });
                } catch (e) {}

                this.mentionQueue.splice(this.mentionIndex, 1);
            },

            appendMessages(incoming) {
                const existing = new Set(this.messages.map(m => m.id));
                let added = false;

                for (const message of incoming) {
                    if (!existing.has(message.id)) {
                        this.messages.push(message);
                        added = true;
                    } else {
                        const idx = this.messages.findIndex(m => m.id === message.id);
                        if (idx >= 0) this.messages[idx] = message;
                    }
                }

                if (added) this.scrollToBottom();
            },

            async poll() {
                try {
                    const separator = this.pollUrl.includes('?') ? '&' : '?';
                    const response = await fetch(`${this.pollUrl}${separator}after=${this.lastId()}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    this.appendMessages(data.messages ?? []);

                    if (this.mentionsUrl) {
                        const mentionResponse = await fetch(this.mentionsUrl, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (mentionResponse.ok) {
                            const mentionData = await mentionResponse.json();
                            this.mentionQueue = mentionData.message_ids ?? [];
                        }
                    }
                } catch (error) {}
            },

            revokeFilePreviews() {
                for (const preview of this.filePreviews) {
                    if (preview.url) URL.revokeObjectURL(preview.url);
                }
                this.filePreviews = [];
            },

            formatBytes(bytes) {
                if (!bytes && bytes !== 0) return '';
                const units = ['B', 'KB', 'MB', 'GB'];
                let size = bytes;
                let unit = 0;
                while (size >= 1024 && unit < units.length - 1) {
                    size /= 1024;
                    unit++;
                }
                return `${unit === 0 ? size : size.toFixed(1)} ${units[unit]}`;
            },

            fileKey(file) {
                return [file.name, file.size, file.lastModified].join('::');
            },

            buildPreview(file) {
                const isImage = file.type.startsWith('image/');
                const isVideo = file.type.startsWith('video/');
                return {
                    key: this.fileKey(file),
                    name: file.name,
                    url: (isImage || isVideo) ? URL.createObjectURL(file) : null,
                    isImage,
                    isVideo,
                    sizeLabel: this.formatBytes(file.size),
                    ext: (file.name.split('.').pop() || 'file').slice(0, 5).toUpperCase(),
                };
            },

            onFilesChange(event) {
                const incoming = [...(event.target.files || [])];
                if (!incoming.length) return;

                this.sendError = '';
                const existing = new Set(this.files.map(f => this.fileKey(f)));
                const accepted = [];

                for (const file of incoming) {
                    if (file.size > this.maxFileBytes) {
                        this.sendError = `"${file.name}" is larger than 50 MB.`;
                        continue;
                    }
                    const key = this.fileKey(file);
                    if (existing.has(key)) continue;
                    existing.add(key);
                    accepted.push(file);
                }

                for (const file of accepted) {
                    this.files.push(file);
                    this.filePreviews.push(this.buildPreview(file));
                }

                // Reset input so the same files can be re-picked later if needed,
                // without re-injecting already staged files on the next change.
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            },

            removeFile(index) {
                if (this.filePreviews[index]?.url) {
                    URL.revokeObjectURL(this.filePreviews[index].url);
                }
                this.files.splice(index, 1);
                this.filePreviews.splice(index, 1);
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            },

            startEdit(message) {
                this.editingId = message.id;
                this.editDraft = message.content || '';
            },

            cancelEdit() {
                this.editingId = null;
                this.editDraft = '';
            },

            async saveEdit(messageId) {
                if (!this.editDraft.trim()) return;

                try {
                    const response = await fetch(this.updateUrl(messageId), {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ content: this.editDraft.trim() }),
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    const idx = this.messages.findIndex(m => m.id === messageId);
                    if (idx >= 0 && data.message) {
                        this.messages[idx] = data.message;
                    }
                    this.cancelEdit();
                } catch (error) {}
            },

            openCall(type) {
                this.callType = type;
                this.showCallModal = true;
            },

            async sendMessage() {
                if (this.sending) return;
                if (!this.draft.trim() && !this.files.length) return;

                this.sending = true;
                this.sendError = '';
                const formData = new FormData();

                if (this.draft.trim()) {
                    formData.append('content', this.draft.trim());
                }

                for (const file of this.files) {
                    formData.append('files[]', file);
                }

                for (const id of this.selectedUserIds) {
                    formData.append('mention_user_ids[]', id);
                }

                if (this.parentId) {
                    formData.append('parent_id', this.parentId);
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

                    if (!response.ok) {
                        let message = 'Could not send message.';
                        try {
                            const err = await response.json();
                            const first = err.message
                                || Object.values(err.errors || {}).flat()[0];
                            if (first) message = first;
                        } catch (e) {}
                        this.sendError = message;
                        return;
                    }

                    const data = await response.json();
                    if (data.message) {
                        this.appendMessages([data.message]);
                    }
                    this.draft = '';
                    this.revokeFilePreviews();
                    this.files = [];
                    this.selectedUserIds = [];
                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                } catch (error) {
                    this.sendError = 'Network error while sending. Try again.';
                } finally {
                    this.sending = false;
                    this.$nextTick(() => this.focusDraft());
                }
            },
        };
    }
</script>
@endverbatim
