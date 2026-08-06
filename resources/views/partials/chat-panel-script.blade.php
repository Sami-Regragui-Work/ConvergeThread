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
            callSignalUrl: config.callSignalUrl ?? null,
            callActiveUrl: config.callActiveUrl ?? null,
            currentUserName: config.currentUserName ?? 'You',
            parentMessage: config.parentMessage ?? null,
            activeCall: config.activeCall ?? null,
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
            callId: null,
            callState: 'idle',
            callError: '',
            incomingCall: null,
            localStream: null,
            localMuted: false,
            localVideoOff: false,
            peers: [],
            peerConnections: {},
            callPollTimer: null,
            iceServers: config.iceServers ?? [{ urls: 'stun:stun.l.google.com:19302' }],
            focusMessageId: null,

            async init() {
                const params = new URLSearchParams(window.location.search);
                const focus = params.get('message');
                if (focus) this.focusMessageId = Number(focus);

                this.scrollToBottom();
                this.$nextTick(() => this.focusDraft());
                this.setupRealtime();
                this.pollTimer = setInterval(() => this.poll(), this.echoBound ? 15000 : 3000);
                this.callPollTimer = setInterval(() => this.refreshActiveCall(), 8000);
                await this.setupE2ee();
                if (this.parentMessage) {
                    await this.decryptMessageInPlace(this.parentMessage);
                    this.parentMessage = { ...this.parentMessage };
                }
                await this.decryptMessages(this.messages);
                await this.indexMessagesForSearch(this.messages);
                await this.refreshActiveCall();
                if (params.get('join_call') === '1' && this.activeCall) {
                    await this.joinActiveCall();
                }
                this.$nextTick(() => this.scrollToFocusedMessage());
            },

            destroy() {
                if (this.pollTimer) clearInterval(this.pollTimer);
                if (this.callPollTimer) clearInterval(this.callPollTimer);
                if (this.echoBound && window.Echo && this.chatType && this.chatId) {
                    window.Echo.leave('chat.' + this.chatType + '.' + this.chatId);
                }
                this.teardownCall();
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
                        try {
                            await fetch(this.cryptoShowUrl.replace(/\/crypto$/, '/crypto/request-key'), {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                credentials: 'same-origin',
                            });
                        } catch (e) {}
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

            async indexMessagesForSearch(list) {
                if (!window.ChatSearchIndex || !this.chatType || !this.chatId || !Array.isArray(list)) return;
                for (const message of list) {
                    try {
                        await window.ChatSearchIndex.indexDecryptedMessage(
                            this.chatType,
                            this.chatId,
                            message,
                            this.currentUserId,
                        );
                    } catch (e) {}
                }
            },

            downloadAttachment(attachment) {
                if (!attachment) return;
                const url = attachment.local_url || attachment.url;
                if (!url) return;
                const a = document.createElement('a');
                a.href = url;
                a.download = attachment.name || 'download';
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                a.remove();
            },

            scrollToFocusedMessage() {
                if (!this.focusMessageId) return;
                this.scrollToMessage(this.focusMessageId);
                const url = new URL(window.location.href);
                url.searchParams.delete('message');
                window.history.replaceState({}, '', url.pathname + url.search + url.hash);
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

                const channel = window.Echo.private('chat.' + this.chatType + '.' + this.chatId);

                channel.listen('.message.sent', (e) => {
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

                channel.listen('.call.signal', (payload) => this.onCallSignal(payload));
                channel.listen('.chat.key.needed', async (payload) => {
                    if (!this.roomKey || !payload?.user_id) return;
                    if (Number(payload.user_id) === Number(this.currentUserId)) return;
                    await this.distributeMissingShares([]);
                });

                this.echoBound = true;
            },

            async refreshActiveCall() {
                if (!this.callActiveUrl) return;
                try {
                    const res = await fetch(this.callActiveUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.activeCall = data.active || null;
                } catch (e) {}
            },

            async joinActiveCall() {
                if (!this.activeCall || this.callState !== 'idle') return;
                this.incomingCall = {
                    call_id: this.activeCall.call_id,
                    call_type: this.activeCall.call_type,
                    from_user_id: this.activeCall.from_user_id,
                    from_user_name: this.activeCall.from_user_name,
                };
                await this.acceptIncoming();
                const url = new URL(window.location.href);
                url.searchParams.delete('join_call');
                window.history.replaceState({}, '', url.pathname + url.search + url.hash);
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
                        this.indexMessagesForSearch([decoded]);
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
                this.startCall(type);
            },

            async signalCall(payload) {
                if (!this.callSignalUrl) return;
                await fetch(this.callSignalUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
            },

            async ensureLocalMedia(type) {
                if (this.localStream) {
                    this.localStream.getTracks().forEach((t) => t.stop());
                    this.localStream = null;
                }

                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: type === 'video',
                });
                this.localMuted = false;
                this.localVideoOff = false;
                this.$nextTick(() => {
                    if (this.$refs.localVideo) this.$refs.localVideo.srcObject = this.localStream;
                });
            },

            async startCall(type) {
                if (this.callState !== 'idle' || !this.callSignalUrl) {
                    this.callError = this.callSignalUrl ? 'Already in a call.' : 'Calling requires realtime (Reverb).';
                    this.showCallModal = true;
                    return;
                }
                if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                    this.callError = 'Mic/camera need HTTPS (or localhost).';
                    this.showCallModal = true;
                    return;
                }
                if (!window.Echo || !this.echoBound) {
                    this.callError = 'Live calls need Reverb (composer run serve). You can still try; others may miss the ring.';
                }

                try {
                    this.callType = type;
                    this.callId = 'call_' + this.currentUserId + '_' + Date.now();
                    this.callState = 'outgoing';
                    this.callError = this.callError || '';
                    this.showCallModal = true;
                    this.peers = [];
                    this.peerConnections = {};
                    await this.ensureLocalMedia(type);
                    await this.signalCall({
                        action: 'invite',
                        call_id: this.callId,
                        call_type: type,
                    });
                } catch (e) {
                    this.callError = 'Microphone/camera permission denied or unavailable.';
                    this.callState = 'idle';
                    this.showCallModal = true;
                }
            },

            async acceptIncoming() {
                if (!this.incomingCall) return;
                try {
                    this.callId = this.incomingCall.call_id;
                    this.callType = this.incomingCall.call_type;
                    this.callState = 'active';
                    this.callError = '';
                    this.showCallModal = true;
                    this.peers = [];
                    this.peerConnections = {};
                    await this.ensureLocalMedia(this.callType);
                    const fromId = this.incomingCall.from_user_id;
                    const fromName = this.incomingCall.from_user_name;
                    this.incomingCall = null;
                    await this.signalCall({
                        action: 'join',
                        call_id: this.callId,
                        call_type: this.callType,
                    });
                    await this.createOfferFor(fromId, fromName);
                } catch (e) {
                    this.callError = 'Could not access microphone/camera.';
                    this.incomingCall = null;
                    this.callState = 'idle';
                }
            },

            async rejectIncoming() {
                if (!this.incomingCall) return;
                await this.signalCall({
                    action: 'reject',
                    call_id: this.incomingCall.call_id,
                    call_type: this.incomingCall.call_type,
                    to_user_id: this.incomingCall.from_user_id,
                });
                this.incomingCall = null;
            },

            async endCall() {
                const id = this.callId;
                const type = this.callType;
                if (id) {
                    try {
                        await this.signalCall({ action: 'leave', call_id: id, call_type: type || 'voice' });
                    } catch (e) {}
                }
                this.teardownCall();
            },

            teardownCall() {
                Object.values(this.peerConnections).forEach((pc) => {
                    try { pc.close(); } catch (e) {}
                });
                this.peerConnections = {};
                this.peers = [];
                if (this.localStream) {
                    this.localStream.getTracks().forEach((t) => t.stop());
                    this.localStream = null;
                }
                this.callId = null;
                this.callState = 'idle';
                this.showCallModal = false;
                this.incomingCall = null;
                this.callError = '';
            },

            toggleMute() {
                if (!this.localStream) return;
                this.localMuted = !this.localMuted;
                this.localStream.getAudioTracks().forEach((t) => { t.enabled = !this.localMuted; });
            },

            toggleVideo() {
                if (!this.localStream || this.callType !== 'video') return;
                this.localVideoOff = !this.localVideoOff;
                this.localStream.getVideoTracks().forEach((t) => { t.enabled = !this.localVideoOff; });
            },

            upsertPeer(userId, name, stream = null) {
                const idx = this.peers.findIndex((p) => Number(p.userId) === Number(userId));
                if (idx >= 0) {
                    this.peers[idx] = {
                        ...this.peers[idx],
                        name: name || this.peers[idx].name,
                        stream: stream || this.peers[idx].stream,
                    };
                } else {
                    this.peers.push({ userId: Number(userId), name: name || ('User #' + userId), stream });
                }
                this.peers = [...this.peers];
                this.$nextTick(() => {
                    const video = document.getElementById('remote-video-' + userId);
                    const audio = document.getElementById('remote-audio-' + userId);
                    const peer = this.peers.find((p) => Number(p.userId) === Number(userId));
                    if (peer?.stream) {
                        if (video) video.srcObject = peer.stream;
                        if (audio) audio.srcObject = peer.stream;
                    }
                });
            },

            removePeer(userId) {
                const pc = this.peerConnections[userId];
                if (pc) {
                    try { pc.close(); } catch (e) {}
                    delete this.peerConnections[userId];
                }
                this.peers = this.peers.filter((p) => Number(p.userId) !== Number(userId));
            },

            createPeerConnection(userId, name) {
                if (this.peerConnections[userId]) return this.peerConnections[userId];

                const pc = new RTCPeerConnection({ iceServers: this.iceServers });
                this.peerConnections[userId] = pc;

                if (this.localStream) {
                    this.localStream.getTracks().forEach((track) => pc.addTrack(track, this.localStream));
                }

                pc.onicecandidate = (event) => {
                    if (!event.candidate || !this.callId) return;
                    this.signalCall({
                        action: 'ice',
                        call_id: this.callId,
                        call_type: this.callType,
                        to_user_id: Number(userId),
                        candidate: event.candidate.toJSON(),
                    });
                };

                pc.ontrack = (event) => {
                    const stream = event.streams[0] || new MediaStream([event.track]);
                    this.upsertPeer(userId, name, stream);
                };

                pc.onconnectionstatechange = () => {
                    if (['failed', 'disconnected', 'closed'].includes(pc.connectionState)) {
                        this.removePeer(userId);
                        if (!this.peers.length && this.callState === 'active') {
                            // keep local UI until hangup
                        }
                    }
                };

                this.upsertPeer(userId, name);
                return pc;
            },

            async createOfferFor(userId, name) {
                const pc = this.createPeerConnection(userId, name);
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await this.signalCall({
                    action: 'offer',
                    call_id: this.callId,
                    call_type: this.callType,
                    to_user_id: Number(userId),
                    sdp: offer,
                });
            },

            async onCallSignal(payload) {
                if (!payload || Number(payload.from_user_id) === Number(this.currentUserId)) return;
                if (payload.to_user_id && Number(payload.to_user_id) !== Number(this.currentUserId)) return;

                const action = payload.action;

                if (action === 'invite') {
                    if (this.callState !== 'idle') return;
                    this.incomingCall = {
                        call_id: payload.call_id,
                        call_type: payload.call_type,
                        from_user_id: payload.from_user_id,
                        from_user_name: payload.from_user_name,
                    };
                    this.activeCall = {
                        call_id: payload.call_id,
                        call_type: payload.call_type,
                        from_user_id: payload.from_user_id,
                        from_user_name: payload.from_user_name,
                    };
                    return;
                }

                if (action === 'leave') {
                    if (this.callId && payload.call_id === this.callId) {
                        this.removePeer(payload.from_user_id);
                        if (!this.peers.length && this.callState === 'active') {
                            this.callError = 'Everyone else left the call.';
                        }
                    }
                    if (this.activeCall && payload.call_id === this.activeCall.call_id) {
                        this.activeCall = null;
                    }
                    return;
                }

                if (action === 'reject') {
                    if (this.callId && payload.call_id === this.callId && this.callState === 'outgoing') {
                        this.callError = (payload.from_user_name || 'Someone') + ' declined the call.';
                    }
                    return;
                }

                if (action === 'join') {
                    if (!this.callId || payload.call_id !== this.callId) return;
                    if (this.callState === 'outgoing') this.callState = 'active';
                    if (!this.localStream) return;
                    await this.createOfferFor(payload.from_user_id, payload.from_user_name);
                    return;
                }

                if (action === 'offer') {
                    if (!this.callId || payload.call_id !== this.callId || !payload.sdp) return;
                    if (this.callState === 'outgoing') this.callState = 'active';
                    const pc = this.createPeerConnection(payload.from_user_id, payload.from_user_name);
                    await pc.setRemoteDescription(payload.sdp);
                    const answer = await pc.createAnswer();
                    await pc.setLocalDescription(answer);
                    await this.signalCall({
                        action: 'answer',
                        call_id: this.callId,
                        call_type: this.callType,
                        to_user_id: Number(payload.from_user_id),
                        sdp: answer,
                    });
                    return;
                }

                if (action === 'answer') {
                    if (!this.callId || payload.call_id !== this.callId || !payload.sdp) return;
                    const pc = this.peerConnections[payload.from_user_id];
                    if (!pc) return;
                    await pc.setRemoteDescription(payload.sdp);
                    if (this.callState === 'outgoing') this.callState = 'active';
                    return;
                }

                if (action === 'ice') {
                    if (!this.callId || payload.call_id !== this.callId || !payload.candidate) return;
                    const pc = this.peerConnections[payload.from_user_id] || this.createPeerConnection(payload.from_user_id, payload.from_user_name);
                    try {
                        await pc.addIceCandidate(payload.candidate);
                    } catch (e) {}
                }
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
