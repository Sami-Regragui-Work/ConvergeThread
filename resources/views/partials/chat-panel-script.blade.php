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
            destroyUrlTemplate: config.destroyUrlTemplate ?? '',
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
            maxFilesPerMessage: 20,
            dragOverComposer: false,
            recording: false,
            recordSeconds: 0,
            mediaRecorder: null,
            recordStream: null,
            recordChunks: [],
            recordTimer: null,
            mediaViewer: null,
            _pdfjsLoading: null,
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
            sharingScreen: false,
            cameraTrack: null,
            screenTrack: null,
            peers: [],
            peerConnections: {},
            peerDisconnectTimers: {},
            callPollTimer: null,
            callHeartbeatTimer: null,
            ringtoneCtx: null,
            ringtoneNodes: null,
            iceServers: config.iceServers ?? [{ urls: 'stun:stun.l.google.com:19302' }],
            focusMessageId: null,

            async init() {
                const params = new URLSearchParams(window.location.search);
                const focus = params.get('message');
                if (focus) this.focusMessageId = Number(focus);

                window.__ctSuppressGlobalCall = (payload) => {
                    if (!payload) return false;
                    if (this.callState !== 'idle') return true;
                    if (this.incomingCall) return true;
                    return String(payload.chat_type) === String(this.chatType)
                        && Number(payload.chat_id) === Number(this.chatId);
                };

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
                if (window.__ctSuppressGlobalCall) window.__ctSuppressGlobalCall = null;
                if (this.pollTimer) clearInterval(this.pollTimer);
                if (this.callPollTimer) clearInterval(this.callPollTimer);
                if (this.callHeartbeatTimer) clearInterval(this.callHeartbeatTimer);
                if (this.recording) this.stopRecording();
                this.stopRingtone();
                this.closeMediaViewer();
                if (this.echoBound && window.Echo && this.chatType && this.chatId) {
                    window.Echo.leave('chat.' + this.chatType + '.' + this.chatId);
                }
                if (this.callId) {
                    const body = JSON.stringify({
                        action: 'leave',
                        call_id: this.callId,
                        call_type: this.callType || 'voice',
                    });
                    try {
                        fetch(this.callSignalUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            credentials: 'same-origin',
                            body,
                            keepalive: true,
                        });
                    } catch (e) {}
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
                    // Survive browser restart: restore room key from local cache first.
                    if (this.chatType && this.chatId) {
                        const cached = await window.ChatCrypto.loadCachedRoomKey(
                            this.currentUserId,
                            this.chatType,
                            this.chatId,
                        );
                        if (cached) {
                            this.roomKey = cached;
                            this.e2eeReady = true;
                            this.e2eeError = '';
                        }
                    }

                    this.identity = await window.ChatCrypto.ensureIdentity(this.currentUserId, this.cryptoPublicKeyUrl);
                    const stateRes = await fetch(this.cryptoShowUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!stateRes.ok) throw new Error('Could not load chat keys');
                    const state = await stateRes.json();

                    if (!this.roomKey && state.my_share) {
                        try {
                            this.roomKey = await window.ChatCrypto.unwrapRoomKey(
                                this.identity.privateKey,
                                state.my_share.wrapped_key,
                                state.my_share.ephemeral_public_key,
                            );
                        } catch (unwrapErr) {
                            console.warn('E2EE unwrap failed (stale share or new identity)', unwrapErr);
                            // Identity rotated (e.g. new Firefox container) — ask peers to re-wrap.
                            this.roomKey = null;
                            if (this.chatType && this.chatId) {
                                window.ChatCrypto.clearCachedRoomKey(this.currentUserId, this.chatType, this.chatId);
                            }
                            await this.requestChatKeyShare();
                            this.e2eeReady = false;
                            this.e2eeError = 'Unlocking chat keys… another online member may need to be present briefly.';
                            this.scheduleE2eeRetry();
                            return;
                        }
                    } else if (!this.roomKey && state.has_room_key) {
                        this.e2eeReady = false;
                        this.e2eeError = 'Unlocking chat keys…';
                        await this.requestChatKeyShare();
                        this.scheduleE2eeRetry();
                        return;
                    } else if (!this.roomKey) {
                        this.roomKey = await window.ChatCrypto.generateRoomKey();
                    }

                    if (this.identity?.minted && state.has_room_key) {
                        await this.requestChatKeyShare();
                    }

                    try {
                        await this.distributeMissingShares(state.participants || []);
                    } catch (shareErr) {
                        console.warn('E2EE share distribute failed', shareErr);
                    }

                    if (this.roomKey && this.chatType && this.chatId) {
                        await window.ChatCrypto.cacheRoomKey(
                            this.currentUserId,
                            this.chatType,
                            this.chatId,
                            this.roomKey,
                        );
                    }

                    this.e2eeReady = !!this.roomKey;
                    this.e2eeError = this.e2eeReady ? '' : (this.e2eeError || 'Unlocking chat keys…');
                } catch (e) {
                    console.error('E2EE setup failed', e);
                    this.e2eeReady = !!this.roomKey;
                    this.e2eeError = this.roomKey
                        ? ''
                        : 'Unlocking chat keys…';
                    if (!this.roomKey) this.scheduleE2eeRetry();
                }
            },

            scheduleE2eeRetry() {
                if (this._e2eeRetryTimer) clearTimeout(this._e2eeRetryTimer);
                this._e2eeRetryTimer = setTimeout(() => {
                    this.setupE2ee().then(() => {
                        if (this.e2eeReady) this.decryptMessages(this.messages);
                    });
                }, 3000);
            },

            async requestChatKeyShare() {
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
                if (list === this.messages) {
                    this.messages = [...this.messages];
                }
                if (this.parentMessage) {
                    this.parentMessage = { ...this.parentMessage };
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
                const url = this.attachmentDisplayUrl(attachment) || attachment.url;
                if (!url) return;
                const a = document.createElement('a');
                a.href = url;
                a.download = attachment.name || 'download';
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                a.remove();
            },

            attachmentDisplayUrl(attachment) {
                if (!attachment) return null;
                if (attachment.local_url) return attachment.local_url;
                if (attachment.is_encrypted) return null;
                return attachment.preview_url || attachment.url || null;
            },

            attachmentMetaLine(attachment) {
                if (!attachment) return '';
                const parts = [];
                if (attachment.page_count) parts.push(attachment.page_count + ' pages');
                parts.push((attachment.ext || attachment.kind || 'FILE').toUpperCase());
                if (attachment.size_label) parts.push(attachment.size_label);
                return parts.join(' · ');
            },

            openMediaViewer(attachment) {
                const url = this.attachmentDisplayUrl(attachment) || attachment?.url;
                if (!url) return;
                let type = 'file';
                if (attachment.is_image) type = 'image';
                else if (attachment.is_video) type = 'video';
                else if (attachment.is_audio) type = 'audio';
                else if (attachment.kind === 'pdf' || (attachment.mime_type || '').includes('pdf')) type = 'pdf';
                this.mediaViewer = {
                    url,
                    type,
                    name: attachment.name || 'Media',
                    meta: this.attachmentMetaLine(attachment),
                };
            },

            closeMediaViewer() {
                this.mediaViewer = null;
            },

            async loadPdfJs() {
                if (window.pdfjsLib) return window.pdfjsLib;
                if (this._pdfjsLoading) return this._pdfjsLoading;
                this._pdfjsLoading = new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
                    script.onload = () => {
                        if (!window.pdfjsLib) {
                            reject(new Error('pdf.js missing'));
                            return;
                        }
                        window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                        resolve(window.pdfjsLib);
                    };
                    script.onerror = () => reject(new Error('pdf.js failed to load'));
                    document.head.appendChild(script);
                });
                return this._pdfjsLoading;
            },

            async enrichPdfPreview(attachment) {
                if (!attachment || attachment.kind !== 'pdf' || attachment.thumb_url) return;
                const url = this.attachmentDisplayUrl(attachment);
                if (!url) return;
                try {
                    const pdfjs = await this.loadPdfJs();
                    const doc = await pdfjs.getDocument(
                        String(url).startsWith('blob:') ? url : { url, withCredentials: true }
                    ).promise;
                    attachment.page_count = doc.numPages || null;
                    const page = await doc.getPage(1);
                    const viewport = page.getViewport({ scale: 0.6 });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                    attachment.thumb_url = canvas.toDataURL('image/jpeg', 0.72);
                } catch (e) {
                    // Preview remains icon-only when PDF.js can't render (e.g. encrypted blob quirks).
                }
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
                        // Never paint ciphertext as an image/video src (was causing a tinted broken preview).
                        attachment.preview_url = null;
                        try {
                            const res = await fetch(attachment.url, { credentials: 'same-origin' });
                            if (!res.ok) continue;
                            const buf = await res.arrayBuffer();
                            const plain = await window.ChatCrypto.decryptBytes(this.roomKey, attachment.encryption_iv, buf);
                            const blob = new Blob([plain], { type: attachment.mime_type || 'application/octet-stream' });
                            attachment.local_url = URL.createObjectURL(blob);
                            if (attachment.is_image || attachment.is_video || attachment.is_audio) {
                                attachment.preview_url = attachment.local_url;
                            }
                            attachment.url = attachment.local_url;
                        } catch (e) {}
                    }
                }

                if (Array.isArray(message.attachments)) {
                    for (const attachment of message.attachments) {
                        if (attachment.kind === 'pdf') {
                            await this.enrichPdfPreview(attachment);
                        }
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

            soundsMuted() {
                try {
                    return localStorage.getItem('ct_sounds_muted') === '1';
                } catch (e) {
                    return false;
                }
            },

            startRingtone() {
                this.stopRingtone();
                if (this.soundsMuted()) return;
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return;
                    const ctx = new Ctx();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = 880;
                    gain.gain.value = 0.0001;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start();
                    const pulse = () => {
                        if (!this.ringtoneNodes) return;
                        const now = ctx.currentTime;
                        gain.gain.cancelScheduledValues(now);
                        gain.gain.setValueAtTime(0.0001, now);
                        gain.gain.linearRampToValueAtTime(0.08, now + 0.05);
                        gain.gain.linearRampToValueAtTime(0.0001, now + 0.35);
                    };
                    pulse();
                    const timer = setInterval(pulse, 900);
                    this.ringtoneCtx = ctx;
                    this.ringtoneNodes = { osc, gain, timer };
                } catch (e) {}
            },

            stopRingtone() {
                if (this.ringtoneNodes?.timer) clearInterval(this.ringtoneNodes.timer);
                try { this.ringtoneNodes?.osc?.stop(); } catch (e) {}
                try { this.ringtoneCtx?.close(); } catch (e) {}
                this.ringtoneNodes = null;
                this.ringtoneCtx = null;
            },

            startCallHeartbeat() {
                if (this.callHeartbeatTimer) clearInterval(this.callHeartbeatTimer);
                this.callHeartbeatTimer = setInterval(() => {
                    if (!this.callId || this.callState === 'idle') return;
                    this.signalCall({
                        action: 'heartbeat',
                        call_id: this.callId,
                        call_type: this.callType || 'voice',
                    });
                }, 20000);
            },

            stopCallHeartbeat() {
                if (this.callHeartbeatTimer) {
                    clearInterval(this.callHeartbeatTimer);
                    this.callHeartbeatTimer = null;
                }
            },

            attachRemoteMedia(userId, stream) {
                this.$nextTick(() => {
                    const video = document.getElementById('remote-video-' + userId);
                    const audio = document.getElementById('remote-audio-' + userId);
                    if (video && stream) {
                        video.srcObject = stream;
                        video.play?.().catch(() => {});
                    }
                    if (audio && stream) {
                        audio.srcObject = stream;
                        audio.muted = this.callType === 'video';
                        if (this.callType !== 'video') audio.play?.().catch(() => {});
                    }
                });
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

            destroyUrl(messageId) {
                return this.destroyUrlTemplate.replace('__ID__', messageId);
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
                const isImage = (file.type || '').startsWith('image/');
                const isVideo = (file.type || '').startsWith('video/');
                const isAudio = (file.type || '').startsWith('audio/');
                return {
                    key: this.fileKey(file),
                    name: file.name,
                    url: (isImage || isVideo || isAudio) ? URL.createObjectURL(file) : null,
                    isImage,
                    isVideo,
                    isAudio,
                    sizeLabel: this.formatBytes(file.size),
                    ext: (file.name.split('.').pop() || 'file').slice(0, 5).toUpperCase(),
                };
            },

            addFiles(incoming) {
                const list = [...(incoming || [])].filter(Boolean);
                if (!list.length) return;

                this.sendError = '';
                const existing = new Set(this.files.map((f) => this.fileKey(f)));
                const accepted = [];

                for (const file of list) {
                    if (this.files.length + accepted.length >= this.maxFilesPerMessage) {
                        this.sendError = 'You can attach at most 20 files per message.';
                        break;
                    }
                    if (file.size > this.maxFileBytes) {
                        this.sendError = `"${file.name || 'File'}" is larger than 50 MB.`;
                        continue;
                    }
                    let named = file;
                    if (!file.name || file.name === 'image.png' || file.name === 'blob') {
                        const ext = (file.type || '').split('/')[1] || 'bin';
                        const stamp = new Date().toISOString().replace(/[:.]/g, '-');
                        named = new File([file], `paste-${stamp}.${ext}`, { type: file.type || 'application/octet-stream' });
                    }
                    const key = this.fileKey(named);
                    if (existing.has(key)) continue;
                    existing.add(key);
                    accepted.push(named);
                }

                for (const file of accepted) {
                    this.files.push(file);
                    this.filePreviews.push(this.buildPreview(file));
                }
            },

            onFilesChange(event) {
                this.addFiles(event.target.files || []);
                if (this.$refs.fileInput) this.$refs.fileInput.value = '';
            },

            onComposerPaste(event) {
                const items = [...(event.clipboardData?.items || [])];
                const files = [];
                for (const item of items) {
                    if (item.kind !== 'file') continue;
                    const file = item.getAsFile();
                    if (file) files.push(file);
                }
                if (!files.length) return;
                event.preventDefault();
                this.addFiles(files);
            },

            onComposerDrop(event) {
                this.dragOverComposer = false;
                const files = [...(event.dataTransfer?.files || [])];
                if (!files.length) return;
                this.addFiles(files);
            },

            async toggleRecording() {
                if (this.recording) {
                    this.stopRecording();
                    return;
                }
                await this.startRecording();
            },

            async startRecording() {
                if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
                    this.sendError = 'Audio recording is not supported in this browser.';
                    return;
                }
                try {
                    this.recordStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const mime = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                        ? 'audio/webm;codecs=opus'
                        : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '');
                    this.mediaRecorder = mime
                        ? new MediaRecorder(this.recordStream, { mimeType: mime })
                        : new MediaRecorder(this.recordStream);
                    this.recordChunks = [];
                    this.mediaRecorder.ondataavailable = (e) => {
                        if (e.data && e.data.size) this.recordChunks.push(e.data);
                    };
                    this.mediaRecorder.onstop = () => {
                        const type = this.mediaRecorder?.mimeType || 'audio/webm';
                        const blob = new Blob(this.recordChunks, { type });
                        const ext = type.includes('ogg') ? 'ogg' : 'webm';
                        const file = new File([blob], `voice-${Date.now()}.${ext}`, { type });
                        if (blob.size > 0) this.addFiles([file]);
                        this.recordChunks = [];
                        if (this.recordStream) {
                            this.recordStream.getTracks().forEach((t) => t.stop());
                            this.recordStream = null;
                        }
                    };
                    this.mediaRecorder.start();
                    this.recording = true;
                    this.recordSeconds = 0;
                    this.recordTimer = setInterval(() => { this.recordSeconds += 1; }, 1000);
                } catch (e) {
                    this.sendError = 'Microphone permission denied for recording.';
                    this.recording = false;
                }
            },

            stopRecording() {
                if (this.recordTimer) {
                    clearInterval(this.recordTimer);
                    this.recordTimer = null;
                }
                this.recording = false;
                if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                    try { this.mediaRecorder.stop(); } catch (e) {}
                } else if (this.recordStream) {
                    this.recordStream.getTracks().forEach((t) => t.stop());
                    this.recordStream = null;
                }
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

            askDelete(message) {
                if (!message?.can_delete) return;
                const hasReplies = (message.reply_count || 0) > 0
                    || (this.parentMessage && this.parentMessage.id === message.id && this.messages.length > 0);
                const text = hasReplies
                    ? 'Delete this message and all its replies? This cannot be undone.'
                    : 'Delete this message? This cannot be undone.';
                this.$dispatch('confirm-action', {
                    message: text,
                    onConfirm: () => this.deleteMessage(message),
                });
            },

            async deleteMessage(message) {
                if (!message?.id || !this.destroyUrlTemplate) return;
                try {
                    const response = await fetch(this.destroyUrl(message.id), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) return;
                    const data = await response.json().catch(() => ({}));

                    if (this.parentMessage && this.parentMessage.id === message.id) {
                        window.location.href = data.redirect
                            || ('/messages/' + this.chatType + '/' + this.chatId);
                        return;
                    }

                    this.messages = this.messages.filter((m) => m.id !== message.id);
                    if (this.editingId === message.id) this.cancelEdit();
                    this.mentionQueue = this.mentionQueue.filter((id) => id !== message.id);
                } catch (e) {}
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
                if (!this.callSignalUrl) return null;
                try {
                    const res = await fetch(this.callSignalUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const msg = data?.errors?.call?.[0] || data?.message || 'Call signal failed.';
                        this.callError = msg;
                        return { ok: false, ...data };
                    }
                    return data;
                } catch (e) {
                    return null;
                }
            },

            async ensureLocalMedia(type) {
                if (this.localStream) {
                    this.localStream.getTracks().forEach((t) => t.stop());
                    this.localStream = null;
                }

                this.localStream = await navigator.mediaDevices.getUserMedia({
                    // Disable AEC: same-machine Firefox containers otherwise silence each other.
                    audio: {
                        echoCancellation: false,
                        noiseSuppression: true,
                        autoGainControl: true,
                    },
                    video: type === 'video',
                });
                this.localMuted = false;
                this.localVideoOff = false;
                this.sharingScreen = false;
                this.screenTrack = null;
                this.cameraTrack = this.localStream.getVideoTracks()[0] || null;
                this.bindLocalPreview();
            },

            async startCall(type) {
                if (this.callState !== 'idle' || !this.callSignalUrl) {
                    this.callError = this.callSignalUrl ? 'Already in a call.' : 'Calling requires realtime (Reverb).';
                    this.showCallModal = true;
                    return;
                }
                if (window.__ctInCall) {
                    this.callError = 'You are already in another call.';
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
                    this.startCallHeartbeat();
                    window.__ctSetInCall?.(true);
                    const res = await this.signalCall({
                        action: 'invite',
                        call_id: this.callId,
                        call_type: type,
                    });
                    if (res && res.ok === false) {
                        this.teardownCall();
                        this.showCallModal = true;
                    }
                } catch (e) {
                    this.callError = 'Microphone/camera permission denied or unavailable.';
                    this.teardownCall();
                    this.showCallModal = true;
                }
            },

            async acceptIncoming() {
                if (!this.incomingCall) return;
                if (window.__ctInCall && this.callState === 'idle') {
                    this.callError = 'You are already in another call.';
                    return;
                }
                this.stopRingtone();
                try {
                    this.callId = this.incomingCall.call_id;
                    this.callType = this.incomingCall.call_type;
                    this.callState = 'active';
                    this.callError = '';
                    this.showCallModal = true;
                    this.peers = [];
                    this.peerConnections = {};
                    await this.ensureLocalMedia(this.callType);
                    this.incomingCall = null;
                    this.startCallHeartbeat();
                    window.__ctSetInCall?.(true);
                    const res = await this.signalCall({
                        action: 'join',
                        call_id: this.callId,
                        call_type: this.callType,
                    });
                    if (res && res.ok === false) {
                        this.teardownCall();
                        this.showCallModal = true;
                    }
                } catch (e) {
                    this.callError = 'Could not access microphone/camera.';
                    this.incomingCall = null;
                    this.teardownCall();
                }
            },

            async rejectIncoming() {
                if (!this.incomingCall) return;
                this.stopRingtone();
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
                this.stopRingtone();
                this.stopCallHeartbeat();
                if (id) {
                    try {
                        const res = await this.signalCall({ action: 'leave', call_id: id, call_type: type || 'voice' });
                        if (res?.session_ended) this.activeCall = null;
                    } catch (e) {}
                }
                this.teardownCall();
            },

            teardownCall() {
                this.stopRingtone();
                this.stopCallHeartbeat();
                window.__ctSetInCall?.(false);
                Object.values(this.peerDisconnectTimers || {}).forEach((t) => clearTimeout(t));
                this.peerDisconnectTimers = {};
                Object.values(this.peerConnections).forEach((pc) => {
                    try { pc.close(); } catch (e) {}
                });
                this.peerConnections = {};
                this.peers = [];
                if (this.screenTrack) {
                    try { this.screenTrack.stop(); } catch (e) {}
                }
                this.screenTrack = null;
                this.cameraTrack = null;
                this.sharingScreen = false;
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
                if (!this.localStream || this.callType !== 'video' || this.sharingScreen) return;
                this.localVideoOff = !this.localVideoOff;
                this.localStream.getVideoTracks().forEach((t) => { t.enabled = !this.localVideoOff; });
            },

            localShowsVideo() {
                return this.callType === 'video' || this.sharingScreen;
            },

            peerShowsVideo(peer) {
                if (this.callType === 'video') return true;
                const tracks = peer?.stream?.getVideoTracks?.() || [];
                return tracks.some((t) => t && t.readyState === 'live');
            },

            bindLocalPreview() {
                this.$nextTick(() => {
                    if (!this.$refs.localVideo) return;
                    if (this.sharingScreen && this.screenTrack) {
                        this.$refs.localVideo.srcObject = new MediaStream([this.screenTrack]);
                    } else {
                        this.$refs.localVideo.srcObject = this.localStream;
                    }
                });
            },

            async replaceVideoTrackForPeers(track) {
                const needsRenegotiate = [];
                for (const [userId, pc] of Object.entries(this.peerConnections || {})) {
                    if (!pc) continue;
                    const videoSender = pc.getSenders().find((s) => s.track?.kind === 'video');
                    if (videoSender) {
                        try {
                            await videoSender.replaceTrack(track);
                        } catch (e) {
                            console.warn(e);
                        }
                    } else if (track) {
                        try {
                            pc.addTrack(track, this.localStream || new MediaStream([track]));
                            needsRenegotiate.push(userId);
                        } catch (e) {
                            console.warn(e);
                        }
                    }
                }
                for (const userId of needsRenegotiate) {
                    await this.renegotiateOffer(userId);
                }
            },

            async renegotiateOffer(userId) {
                const pc = this.peerConnections[userId];
                if (!pc || pc.signalingState !== 'stable' || !this.callId) return;
                try {
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    await this.signalCall({
                        action: 'offer',
                        call_id: this.callId,
                        call_type: this.callType,
                        to_user_id: Number(userId),
                        sdp: offer,
                    });
                } catch (e) {
                    console.warn(e);
                }
            },

            async toggleScreenShare() {
                if (this.sharingScreen) {
                    await this.stopScreenShare();
                } else {
                    await this.startScreenShare();
                }
            },

            async startScreenShare() {
                if (!this.callId || this.callState === 'idle') return;
                if (!navigator.mediaDevices?.getDisplayMedia) {
                    this.callError = 'Screen share is not supported in this browser.';
                    return;
                }
                try {
                    const screenStream = await navigator.mediaDevices.getDisplayMedia({
                        video: { frameRate: 15 },
                        audio: false,
                    });
                    const track = screenStream.getVideoTracks()[0];
                    if (!track) {
                        screenStream.getTracks().forEach((t) => t.stop());
                        this.callError = 'No screen track available.';
                        return;
                    }

                    // Keep camera track so we can restore it after sharing.
                    if (!this.cameraTrack && this.localStream) {
                        this.cameraTrack = this.localStream.getVideoTracks()[0] || null;
                    }

                    this.screenTrack = track;
                    this.sharingScreen = true;
                    this.localVideoOff = false;
                    track.onended = () => {
                        if (this.sharingScreen) this.stopScreenShare();
                    };

                    await this.replaceVideoTrackForPeers(track);
                    this.bindLocalPreview();
                    // Refresh peer tiles so remote video visibility updates when others share.
                    this.peers = [...this.peers];
                } catch (e) {
                    if (e?.name !== 'NotAllowedError') {
                        this.callError = 'Could not start screen share.';
                    }
                }
            },

            async stopScreenShare() {
                const screen = this.screenTrack;
                this.screenTrack = null;
                this.sharingScreen = false;
                if (screen) {
                    try { screen.stop(); } catch (e) {}
                }

                const restore = (this.callType === 'video' && this.cameraTrack && this.cameraTrack.readyState === 'live')
                    ? this.cameraTrack
                    : null;

                await this.replaceVideoTrackForPeers(restore);
                this.bindLocalPreview();
                this.peers = [...this.peers];
            },

            upsertPeer(userId, name, stream = null) {
                if (this.peerDisconnectTimers[userId]) {
                    clearTimeout(this.peerDisconnectTimers[userId]);
                    delete this.peerDisconnectTimers[userId];
                }
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
                const peer = this.peers.find((p) => Number(p.userId) === Number(userId));
                if (peer?.stream) this.attachRemoteMedia(userId, peer.stream);
            },

            removePeer(userId) {
                if (this.peerDisconnectTimers[userId]) {
                    clearTimeout(this.peerDisconnectTimers[userId]);
                    delete this.peerDisconnectTimers[userId];
                }
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
                    if (this.sharingScreen && this.screenTrack
                        && !this.localStream.getVideoTracks().includes(this.screenTrack)) {
                        pc.addTrack(this.screenTrack, this.localStream);
                    }
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
                    const refresh = () => { this.peers = [...this.peers]; };
                    event.track.addEventListener('ended', refresh);
                    event.track.addEventListener('mute', refresh);
                    event.track.addEventListener('unmute', refresh);
                };

                pc.onconnectionstatechange = () => {
                    const state = pc.connectionState;
                    if (state === 'connected' || state === 'connecting') {
                        if (this.peerDisconnectTimers[userId]) {
                            clearTimeout(this.peerDisconnectTimers[userId]);
                            delete this.peerDisconnectTimers[userId];
                        }
                        return;
                    }
                    if (state === 'failed' || state === 'closed') {
                        this.removePeer(userId);
                        return;
                    }
                    if (state === 'disconnected') {
                        // Firefox containers / same-host ICE often flaps — wait before dropping.
                        if (this.peerDisconnectTimers[userId]) clearTimeout(this.peerDisconnectTimers[userId]);
                        this.peerDisconnectTimers[userId] = setTimeout(() => {
                            const current = this.peerConnections[userId];
                            if (current && ['disconnected', 'failed', 'closed'].includes(current.connectionState)) {
                                this.removePeer(userId);
                            }
                        }, 5000);
                    }
                };

                this.upsertPeer(userId, name);
                return pc;
            },

            async createOfferFor(userId, name) {
                if (this.peerConnections[userId]?.localDescription?.type === 'offer') return;
                const pc = this.createPeerConnection(userId, name);
                if (pc.signalingState !== 'stable') return;
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
                    this.startRingtone();
                    return;
                }

                if (action === 'leave') {
                    if (this.callId && payload.call_id === this.callId) {
                        this.removePeer(payload.from_user_id);
                        if (!this.peers.length && this.callState === 'active') {
                            this.callError = 'Everyone else left the call.';
                        }
                    }
                    // Only clear the join banner when the whole session ended.
                    if (payload.session_ended && this.activeCall && payload.call_id === this.activeCall.call_id) {
                        this.activeCall = null;
                        this.stopRingtone();
                        if (this.incomingCall?.call_id === payload.call_id) {
                            this.incomingCall = null;
                        }
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
                    try {
                        if (pc.signalingState !== 'stable' && pc.localDescription) {
                            // Glare: roll back our uncommitted offer if any, then accept theirs.
                            await pc.setLocalDescription({ type: 'rollback' });
                        }
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
                    } catch (e) {
                        this.callError = 'Could not connect media to peer.';
                    }
                    return;
                }

                if (action === 'answer') {
                    if (!this.callId || payload.call_id !== this.callId || !payload.sdp) return;
                    const pc = this.peerConnections[payload.from_user_id];
                    if (!pc) return;
                    try {
                        if (pc.signalingState === 'have-local-offer') {
                            await pc.setRemoteDescription(payload.sdp);
                        }
                    } catch (e) {}
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
