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
            cryptoShowUrl: config.cryptoShowUrl ?? null,
            cryptoSharesUrl: config.cryptoSharesUrl ?? null,
            cryptoPublicKeyUrl: config.cryptoPublicKeyUrl ?? null,
            draft: '',
            files: [],
            filePreviews: [],
            sending: false,
            sendError: '',
            maxFileBytes: 50 * 1024 * 1024,
            pollTimer: null,
            echoBound: false,
            e2eeReady: false,
            e2eeError: '',
            identity: null,
            roomKey: null,
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

            async init() {
                this.scrollToBottom();
                this.$nextTick(() => this.focusDraft());
                this.setupRealtime();
                this.pollTimer = setInterval(() => this.poll(), this.echoBound ? 15000 : 3000);
                await this.setupE2ee();
                await this.decryptMessages(this.messages);
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

            async setupE2ee() {
                if (!window.ChatCrypto || !this.cryptoShowUrl || !this.cryptoPublicKeyUrl) return;

                try {
                    this.identity = await window.ChatCrypto.ensureIdentity(this.currentUserId, this.cryptoPublicKeyUrl);
                    const stateRes = await fetch(this.cryptoShowUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!stateRes.ok) throw new Error('Could not load chat keys');
                    const state = await stateRes.json();

                    if (state.my_share) {
                        this.roomKey = await window.ChatCrypto.unwrapRoomKey(
                            this.identity.privateKey,
                            state.my_share.wrapped_key,
                            state.my_share.ephemeral_public_key,
                        );
                    } else if (state.has_room_key) {
                        this.e2eeReady = false;
                        this.e2eeError = 'Waiting for an existing member to share the chat key…';
                        setTimeout(() => this.setupE2ee().then(() => {
                            if (this.e2eeReady) this.decryptMessages(this.messages);
                        }), 3000);
                        return;
                    } else {
                        this.roomKey = await window.ChatCrypto.generateRoomKey();
                    }

                    await this.distributeMissingShares(state.participants || []);
                    this.e2eeReady = true;
                    this.e2eeError = '';
                } catch (e) {
                    this.e2eeReady = false;
                    this.e2eeError = 'E2EE unavailable in this browser session.';
                }
            },

            async distributeMissingShares(participants) {
                if (!this.roomKey || !this.cryptoSharesUrl) return;

                const stateRes = await fetch(this.cryptoShowUrl, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!stateRes.ok) return;
                const state = await stateRes.json();
                const shares = [];
                for (const person of (participants.length ? participants : state.participants || [])) {
                    if (!person.public_key) continue;
                    try {
                        const wrapped = await window.ChatCrypto.wrapRoomKeyFor(this.roomKey, person.public_key);
                        shares.push({
                            user_id: person.id,
                            wrapped_key: wrapped.wrapped_key,
                            ephemeral_public_key: wrapped.ephemeral_public_key,
                        });
                    } catch (e) {}
                }

                if (!shares.length) return;

                await fetch(this.cryptoSharesUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ shares }),
                });
            },

            async decryptMessages(list) {
                for (const message of list) {
                    await this.decryptMessageInPlace(message);
                }
            },

            async decryptMessageInPlace(message) {
                if (!message) return message;

                if (this.roomKey && (message.is_encrypted || window.ChatCrypto?.isEncrypted(message.content))) {
                    try {
                        const plain = await window.ChatCrypto.decryptText(this.roomKey, message.content);
                        message.content = plain;
                        message.content_html = null;
                        message.is_encrypted = false;
                        message._was_encrypted = true;
                    } catch (e) {
                        message.content = '[Unable to decrypt]';
                        message.content_html = null;
                    }
                }

                if (this.roomKey && Array.isArray(message.attachments)) {
                    for (const attachment of message.attachments) {
                        if (!attachment.is_encrypted || !attachment.encryption_iv || !attachment.url) continue;
                        if (attachment.local_url) continue;
                        try {
                            const res = await fetch(attachment.url, { credentials: 'same-origin' });
                            if (!res.ok) continue;
                            const buf = await res.arrayBuffer();
                            const plain = await window.ChatCrypto.decryptBytes(this.roomKey, attachment.encryption_iv, buf);
                            const blob = new Blob([plain], { type: attachment.mime_type || 'application/octet-stream' });
                            attachment.local_url = URL.createObjectURL(blob);
                            if (attachment.is_image || attachment.is_video) {
                                attachment.preview_url = attachment.local_url;
                            }
                            attachment.url = attachment.local_url;
                        } catch (e) {}
                    }
                }

                return message;
            },

            resolveMentionUserIds(text) {
                const ids = new Set(this.selectedUserIds.map(Number));
                if (/@all\b/i.test(text)) {
                    this.participants.forEach(p => ids.add(Number(p.id)));
                }
                if (/@selected\b/i.test(text)) {
                    this.selectedUserIds.forEach(id => ids.add(Number(id)));
                }
                for (const person of this.participants) {
                    if (!person.username) continue;
                    const re = new RegExp('@' + person.username.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
                    if (re.test(text)) ids.add(Number(person.id));
                }
                for (const match of text.matchAll(/@role[:.]([A-Za-z0-9_-]+)/gi)) {
                    const role = match[1].toLowerCase();
                    this.participants
                        .filter(p => (p.role || '').toLowerCase() === role)
                        .forEach(p => ids.add(Number(p.id)));
                }
                ids.delete(Number(this.currentUserId));
                return [...ids];
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
                    this.decryptMessageInPlace(message).then((decoded) => {
                        const idx = this.messages.findIndex(m => m.id === decoded.id);
                        if (idx >= 0) {
                            this.messages[idx] = decoded;
                        } else {
                            this.messages.push(decoded);
                            this.scrollToBottom();
                        }
                    });
                    if (!existing.has(message.id)) added = true;
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

                    if (this.e2eeReady && this.roomKey) {
                        await this.distributeMissingShares([]);
                    }

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
                    let content = this.editDraft.trim();
                    if (this.e2eeReady && this.roomKey) {
                        content = await window.ChatCrypto.encryptText(this.roomKey, content);
                    }

                    const response = await fetch(this.updateUrl(messageId), {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ content }),
                    });

                    if (!response.ok) return;

                    const data = await response.json();
                    const idx = this.messages.findIndex(m => m.id === messageId);
                    if (idx >= 0 && data.message) {
                        this.messages[idx] = await this.decryptMessageInPlace(data.message);
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
                const plain = this.draft.trim();
                const mentionIds = this.resolveMentionUserIds(plain);

                try {
                    if (plain) {
                        let content = plain;
                        if (this.e2eeReady && this.roomKey) {
                            content = await window.ChatCrypto.encryptText(this.roomKey, plain);
                        }
                        formData.append('content', content);
                    }

                    if (this.e2eeReady && this.roomKey && this.files.length) {
                        for (let i = 0; i < this.files.length; i++) {
                            const file = this.files[i];
                            const bytes = await file.arrayBuffer();
                            const encrypted = await window.ChatCrypto.encryptBytes(this.roomKey, bytes);
                            const blob = new Blob([encrypted.cipher], { type: 'application/octet-stream' });
                            formData.append('files[]', blob, file.name + '.enc');
                            formData.append(`attachment_meta[${i}][name]`, file.name);
                            formData.append(`attachment_meta[${i}][mime]`, file.type || 'application/octet-stream');
                            formData.append(`attachment_meta[${i}][iv]`, encrypted.iv);
                        }
                    } else {
                        for (const file of this.files) {
                            formData.append('files[]', file);
                        }
                    }

                    for (const id of mentionIds) {
                        formData.append('mention_user_ids[]', id);
                    }

                    if (this.parentId) {
                        formData.append('parent_id', this.parentId);
                    }

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
