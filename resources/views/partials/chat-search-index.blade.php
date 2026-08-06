@verbatim
<script>
    window.ChatSearchIndex = (function () {
        const DB_NAME = 'ct_encrypted_search_v1';
        const DB_VERSION = 1;

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
            return withStore('meta', 'readwrite', (store) => {
                store.put(meta);
            });
        }

        async function upsertMessages(type, id, rows) {
            const ck = chatKey(type, id);
            return withStore('messages', 'readwrite', (store) => {
                rows.forEach((row) => {
                    store.put({
                        key: messageKey(type, id, row.messageId),
                        chatKey: ck,
                        chatType: type,
                        chatId: Number(id),
                        messageId: Number(row.messageId),
                        parentId: row.parentId ? Number(row.parentId) : null,
                        userId: Number(row.userId),
                        userName: row.userName || '',
                        body: row.body || '',
                        attachmentNames: row.attachmentNames || [],
                        hasAttachments: !!row.hasAttachments,
                        createdAt: row.createdAt || null,
                    });
                });
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

        function tokenize(text) {
            return String(text || '')
                .toLowerCase()
                .replace(/[^\p{L}\p{N}@:_./-]+/gu, ' ')
                .split(/\s+/)
                .filter(Boolean);
        }

        function matchesQuery(row, query) {
            const q = String(query || '').trim().toLowerCase();
            if (!q) return true;
            const hay = [
                row.body || '',
                row.userName || '',
                ...(row.attachmentNames || []),
            ].join(' ').toLowerCase();
            if (hay.includes(q)) return true;
            const tokens = tokenize(q);
            return tokens.length > 0 && tokens.every((t) => hay.includes(t));
        }

        async function search(filters) {
            const {
                chatType,
                chatId,
                query = '',
                userId = null,
                hasAttachments = null,
                fromDate = null,
                toDate = null,
                limit = 50,
            } = filters;

            let rows = await loadChatMessages(chatType, chatId);
            rows = rows.filter((row) => {
                if (userId && Number(row.userId) !== Number(userId)) return false;
                if (hasAttachments === true && !row.hasAttachments) return false;
                if (hasAttachments === false && row.hasAttachments) return false;
                if (fromDate && row.createdAt && row.createdAt < fromDate) return false;
                if (toDate && row.createdAt && row.createdAt > toDate) return false;
                return matchesQuery(row, query);
            });

            rows.sort((a, b) => String(b.createdAt || '').localeCompare(String(a.createdAt || '')));
            return rows.slice(0, limit);
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
                await upsertMessages(type, id, rows);
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

        async function indexDecryptedMessage(type, id, message) {
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
            }]);
        }

        return {
            chatKey,
            syncChat,
            search,
            indexDecryptedMessage,
            getMeta,
            resolveRoomKey,
        };
    })();
</script>
@endverbatim
