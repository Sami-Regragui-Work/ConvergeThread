@verbatim
<script>
    window.ChatSearchIndex = (function () {
        const DB_NAME = 'ct_encrypted_search_v2';
        const DB_VERSION = 1;
        let indexCryptoKeyPromise = null;

        function openDb() {
            return new Promise((resolve, reject) => {
                const req = indexedDB.open(DB_NAME, DB_VERSION);
                req.onupgradeneeded = () => {
                    const db = req.result;
                    if (!db.objectStoreNames.contains('meta')) {
                        db.createObjectStore('meta', { keyPath: 'chatKey' });
                    }
                    if (!db.objectStoreNames.contains('messages')) {
                        const store = db.createObjectStore('messages', { keyPath: 'key' });
                        store.createIndex('chatKey', 'chatKey', { unique: false });
                        store.createIndex('createdAt', 'createdAt', { unique: false });
                    }
                };
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error);
            });
        }

        function chatKey(type, id) {
            return String(type) + ':' + String(id);
        }

        function messageKey(type, id, messageId) {
            return chatKey(type, id) + ':' + String(messageId);
        }

        async function withStore(storeName, mode, fn) {
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(storeName, mode);
                const store = tx.objectStore(storeName);
                Promise.resolve(fn(store, tx)).then(resolve, reject);
                tx.oncomplete = () => db.close();
                tx.onerror = () => reject(tx.error);
            });
        }

        async function getMeta(type, id) {
            const key = chatKey(type, id);
            return withStore('meta', 'readonly', (store) => new Promise((resolve, reject) => {
                const req = store.get(key);
                req.onsuccess = () => resolve(req.result || null);
                req.onerror = () => reject(req.error);
            }));
        }

        async function putMeta(meta) {
            return withStore('meta', 'readwrite', (store) => { store.put(meta); });
        }

        async function listMeta() {
            return withStore('meta', 'readonly', (store) => new Promise((resolve, reject) => {
                const req = store.getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            }));
        }

        async function deriveIndexKey(userId) {
            if (indexCryptoKeyPromise) return indexCryptoKeyPromise;
            indexCryptoKeyPromise = (async () => {
                const raw = localStorage.getItem('ct_e2ee_private_' + userId);
                if (!raw || !window.ChatCrypto) return null;
                const material = await crypto.subtle.digest('SHA-256', new TextEncoder().encode('ct-search|' + raw));
                return crypto.subtle.importKey('raw', material, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
            })();
            return indexCryptoKeyPromise;
        }

        async function sealBody(userId, plaintext) {
            const key = await deriveIndexKey(userId);
            if (!key || !window.ChatCrypto) {
                return { bodyEnc: null, bodyPlainFallback: plaintext || '' };
            }
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(plaintext || ''));
            return {
                bodyEnc: window.ChatCrypto.b64encode(iv) + ':' + window.ChatCrypto.b64encode(cipher),
                bodyPlainFallback: null,
            };
        }

        async function openBody(userId, row) {
            if (row.bodyPlainFallback != null && row.bodyPlainFallback !== undefined && !row.bodyEnc) {
                return row.bodyPlainFallback || '';
            }
            if (!row.bodyEnc) return '';
            const key = await deriveIndexKey(userId);
            if (!key || !window.ChatCrypto) return '';
            try {
                const [ivB64, cipherB64] = String(row.bodyEnc).split(':');
                const iv = window.ChatCrypto.b64decode(ivB64);
                const cipher = window.ChatCrypto.b64decode(cipherB64);
                const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, cipher);
                return new TextDecoder().decode(plain);
            } catch (e) {
                return '';
            }
        }

        async function upsertMessages(type, id, rows, userId) {
            const ck = chatKey(type, id);
            const sealed = [];
            for (const row of rows) {
                const sealedBody = await sealBody(userId, row.body || '');
                sealed.push({
                    key: messageKey(type, id, row.messageId),
                    chatKey: ck,
                    chatType: type,
                    chatId: Number(id),
                    messageId: Number(row.messageId),
                    parentId: row.parentId ? Number(row.parentId) : null,
                    userId: Number(row.userId),
                    userName: row.userName || '',
                    bodyEnc: sealedBody.bodyEnc,
                    bodyPlainFallback: sealedBody.bodyPlainFallback,
                    attachmentNames: row.attachmentNames || [],
                    hasAttachments: !!row.hasAttachments,
                    createdAt: row.createdAt || null,
                });
            }
            return withStore('messages', 'readwrite', (store) => {
                sealed.forEach((row) => store.put(row));
            });
        }

        async function loadChatMessages(type, id) {
            const ck = chatKey(type, id);
            return withStore('messages', 'readonly', (store) => new Promise((resolve, reject) => {
                const idx = store.index('chatKey');
                const req = idx.getAll(ck);
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            }));
        }

        async function loadAllMessages() {
            return withStore('messages', 'readonly', (store) => new Promise((resolve, reject) => {
                const req = store.getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            }));
        }

        function tokenize(text) {
            return String(text || '')
                .toLowerCase()
                .replace(/[^\p{L}\p{N}@:_./-]+/gu, ' ')
                .split(/\s+/)
                .filter(Boolean);
        }

        function matchesQuery(row, query, body) {
            const q = String(query || '').trim().toLowerCase();
            if (!q) return true;
            const hay = [body || '', row.userName || '', ...(row.attachmentNames || [])].join(' ').toLowerCase();
            if (hay.includes(q)) return true;
            const tokens = tokenize(q);
            return tokens.length > 0 && tokens.every((t) => hay.includes(t));
        }

        async function filterRows(rows, filters, viewerUserId) {
            const {
                query = '',
                userId = null,
                hasAttachments = null,
                fromDate = null,
                toDate = null,
                limit = 50,
            } = filters;

            const out = [];
            for (const row of rows) {
                if (userId && Number(row.userId) !== Number(userId)) continue;
                if (hasAttachments === true && !row.hasAttachments) continue;
                if (hasAttachments === false && row.hasAttachments) continue;
                if (fromDate && row.createdAt && row.createdAt < fromDate) continue;
                if (toDate && row.createdAt && row.createdAt > toDate) continue;
                const body = await openBody(viewerUserId, row);
                if (!matchesQuery(row, query, body)) continue;
                out.push({ ...row, body });
            }
            out.sort((a, b) => String(b.createdAt || '').localeCompare(String(a.createdAt || '')));
            return out.slice(0, limit);
        }

        async function search(filters) {
            const rows = await loadChatMessages(filters.chatType, filters.chatId);
            return filterRows(rows, filters, filters.viewerUserId || filters.userId || null);
        }

        async function searchAll(filters) {
            const rows = await loadAllMessages();
            return filterRows(rows, filters, filters.viewerUserId);
        }

        async function resolveRoomKey(type, id, userId, cryptoPublicKeyUrl) {
            if (!window.ChatCrypto) return null;
            const identity = await window.ChatCrypto.ensureIdentity(userId, cryptoPublicKeyUrl);
            const res = await fetch('/messages/' + type + '/' + id + '/crypto', {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!res.ok) return null;
            const state = await res.json();
            if (!state.my_share) return null;
            return window.ChatCrypto.unwrapRoomKey(
                identity.privateKey,
                state.my_share.wrapped_key,
                state.my_share.ephemeral_public_key,
            );
        }

        async function decryptBody(roomKey, content, isEncrypted) {
            if (!content) return '';
            if ((isEncrypted || window.ChatCrypto?.isEncrypted(content)) && roomKey) {
                try {
                    return await window.ChatCrypto.decryptText(roomKey, content);
                } catch (e) {
                    return '';
                }
            }
            if (window.ChatCrypto?.isEncrypted(content)) return '';
            return content;
        }

        async function syncChat(type, id, options = {}) {
            const userId = options.userId;
            const cryptoPublicKeyUrl = options.cryptoPublicKeyUrl || '/messages/crypto/public-key';
            const force = !!options.force;

            let meta = await getMeta(type, id);
            let after = force ? 0 : (meta?.lastSyncedId || 0);
            const roomKey = await resolveRoomKey(type, id, userId, cryptoPublicKeyUrl);

            let imported = 0;
            while (true) {
                const url = '/messages/' + type + '/' + id + '/search-feed?after=' + after + '&limit=100';
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('Search feed failed');
                const data = await res.json();
                const batch = data.messages || [];
                if (!batch.length) break;

                const rows = [];
                for (const msg of batch) {
                    const body = await decryptBody(roomKey, msg.content, msg.is_encrypted);
                    rows.push({
                        messageId: msg.id,
                        parentId: msg.parent_id,
                        userId: msg.user_id,
                        userName: msg.user_name,
                        body,
                        attachmentNames: msg.attachment_names || [],
                        hasAttachments: !!msg.has_attachments,
                        createdAt: msg.created_at,
                    });
                }
                await upsertMessages(type, id, rows, userId);
                imported += rows.length;
                after = batch[batch.length - 1].id;
                if (!data.next_after) break;
            }

            await putMeta({
                chatKey: chatKey(type, id),
                chatType: type,
                chatId: Number(id),
                lastSyncedId: after,
                syncedAt: new Date().toISOString(),
            });

            return { imported, lastSyncedId: after };
        }

        async function syncChats(chats, options = {}) {
            let imported = 0;
            for (const chat of chats) {
                try {
                    const result = await syncChat(chat.type, chat.id, options);
                    imported += result.imported;
                } catch (e) {}
            }
            return { imported };
        }

        async function indexDecryptedMessage(type, id, message, userId) {
            if (!message?.id) return;
            await upsertMessages(type, id, [{
                messageId: message.id,
                parentId: message.parent_id,
                userId: message.user_id,
                userName: message.user_name,
                body: message.content || '',
                attachmentNames: (message.attachments || []).map((a) => a.name).filter(Boolean),
                hasAttachments: !!(message.attachments && message.attachments.length),
                createdAt: message.created_at_iso || message.created_at || null,
            }], userId || message.user_id);
        }

        return {
            chatKey,
            syncChat,
            syncChats,
            search,
            searchAll,
            indexDecryptedMessage,
            getMeta,
            listMeta,
            resolveRoomKey,
        };
    })();
</script>
@endverbatim
