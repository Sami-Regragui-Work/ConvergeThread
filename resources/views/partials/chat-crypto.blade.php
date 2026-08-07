@verbatim
<script>
    window.ChatCrypto = (function () {
        const enc = new TextEncoder();
        const dec = new TextDecoder();

        function b64encode(buffer) {
            const bytes = buffer instanceof ArrayBuffer ? new Uint8Array(buffer) : buffer;
            let binary = '';
            bytes.forEach((b) => { binary += String.fromCharCode(b); });
            return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
        }

        function b64decode(str) {
            const pad = str.length % 4 === 0 ? '' : '='.repeat(4 - (str.length % 4));
            const normalized = str.replace(/-/g, '+').replace(/_/g, '/') + pad;
            const binary = atob(normalized);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
            return bytes;
        }

        function storageKey(userId) {
            return 'ct_e2ee_private_' + userId;
        }

        function roomStorageKey(userId, chatType, chatId) {
            return 'ct_e2ee_room_' + userId + '_' + chatType + '_' + chatId;
        }

        function publicFingerprint(jwk) {
            try {
                const obj = typeof jwk === 'string' ? JSON.parse(jwk) : jwk;
                return String(obj.x || '') + '.' + String(obj.y || '');
            } catch (e) {
                return '';
            }
        }

        async function generateIdentity() {
            return crypto.subtle.generateKey(
                { name: 'ECDH', namedCurve: 'P-256' },
                true,
                ['deriveBits']
            );
        }

        async function exportPublicJwk(key) {
            return crypto.subtle.exportKey('jwk', key);
        }

        async function exportPrivateJwk(key) {
            return crypto.subtle.exportKey('jwk', key);
        }

        async function importPublicJwk(jwk) {
            return crypto.subtle.importKey('jwk', typeof jwk === 'string' ? JSON.parse(jwk) : jwk, {
                name: 'ECDH',
                namedCurve: 'P-256',
            }, true, []);
        }

        async function importPrivateJwk(jwk) {
            return crypto.subtle.importKey('jwk', typeof jwk === 'string' ? JSON.parse(jwk) : jwk, {
                name: 'ECDH',
                namedCurve: 'P-256',
            }, true, ['deriveBits']);
        }

        async function ensureIdentity(userId, publicKeyUrl) {
            if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
                throw new Error('Secure context required for E2EE (use HTTPS or localhost).');
            }
            if (!window.crypto?.subtle) {
                throw new Error('Web Crypto API unavailable in this browser.');
            }

            const keyName = storageKey(userId);
            let privateJwk = localStorage.getItem(keyName);
            let keyPair;
            let minted = false;

            if (privateJwk) {
                try {
                    const priv = await importPrivateJwk(privateJwk);
                    const pubJwk = JSON.parse(privateJwk);
                    delete pubJwk.d;
                    delete pubJwk.key_ops;
                    delete pubJwk.ext;
                    pubJwk.key_ops = [];
                    const pub = await importPublicJwk(pubJwk);
                    keyPair = { privateKey: priv, publicKey: pub };
                } catch (e) {
                    localStorage.removeItem(keyName);
                    privateJwk = null;
                }
            }

            if (!privateJwk) {
                keyPair = await generateIdentity();
                privateJwk = JSON.stringify(await exportPrivateJwk(keyPair.privateKey));
                localStorage.setItem(keyName, privateJwk);
                minted = true;
            }

            const publicJwkObj = await exportPublicJwk(keyPair.publicKey);
            const publicJwk = JSON.stringify(publicJwkObj);
            const res = await fetch(publicKeyUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ public_key: publicJwk }),
            });
            if (!res.ok) {
                throw new Error('Could not register E2EE public key with the server.');
            }

            return {
                privateKey: keyPair.privateKey,
                publicKey: keyPair.publicKey,
                publicJwk,
                fingerprint: publicFingerprint(publicJwkObj),
                minted,
            };
        }

        async function deriveWrapKey(privateKey, publicKey) {
            const bits = await crypto.subtle.deriveBits(
                { name: 'ECDH', public: publicKey },
                privateKey,
                256
            );
            return crypto.subtle.importKey('raw', bits, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
        }

        async function generateRoomKey() {
            return crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
        }

        async function exportRoomKeyRaw(roomKey) {
            return crypto.subtle.exportKey('raw', roomKey);
        }

        async function importRoomKeyRaw(raw) {
            return crypto.subtle.importKey('raw', raw, { name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']);
        }

        async function cacheRoomKey(userId, chatType, chatId, roomKey) {
            try {
                const raw = await exportRoomKeyRaw(roomKey);
                localStorage.setItem(roomStorageKey(userId, chatType, chatId), b64encode(raw));
            } catch (e) {}
        }

        async function loadCachedRoomKey(userId, chatType, chatId) {
            try {
                const packed = localStorage.getItem(roomStorageKey(userId, chatType, chatId));
                if (!packed) return null;
                return importRoomKeyRaw(b64decode(packed));
            } catch (e) {
                return null;
            }
        }

        function clearCachedRoomKey(userId, chatType, chatId) {
            try {
                localStorage.removeItem(roomStorageKey(userId, chatType, chatId));
            } catch (e) {}
        }

        async function wrapRoomKeyFor(roomKey, recipientPublicJwk) {
            const ephemeral = await generateIdentity();
            const recipientPublic = await importPublicJwk(recipientPublicJwk);
            const wrapKey = await deriveWrapKey(ephemeral.privateKey, recipientPublic);
            const raw = await exportRoomKeyRaw(roomKey);
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, wrapKey, raw);
            const packed = new Uint8Array(iv.length + cipher.byteLength);
            packed.set(iv, 0);
            packed.set(new Uint8Array(cipher), iv.length);

            return {
                wrapped_key: b64encode(packed),
                ephemeral_public_key: JSON.stringify(await exportPublicJwk(ephemeral.publicKey)),
            };
        }

        async function unwrapRoomKey(privateKey, wrappedKey, ephemeralPublicJwk) {
            const ephemeralPublic = await importPublicJwk(ephemeralPublicJwk);
            const wrapKey = await deriveWrapKey(privateKey, ephemeralPublic);
            const packed = b64decode(wrappedKey);
            const iv = packed.slice(0, 12);
            const cipher = packed.slice(12);
            const raw = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, wrapKey, cipher);
            return importRoomKeyRaw(raw);
        }

        async function encryptText(roomKey, plaintext) {
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, roomKey, enc.encode(plaintext));
            return 'e2ee:v1:' + b64encode(iv) + ':' + b64encode(cipher);
        }

        async function decryptText(roomKey, payload) {
            if (!payload || !payload.startsWith('e2ee:v1:')) return payload;
            const parts = payload.split(':');
            if (parts.length !== 4) throw new Error('Invalid ciphertext');
            const iv = b64decode(parts[2]);
            const cipher = b64decode(parts[3]);
            const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, roomKey, cipher);
            return dec.decode(plain);
        }

        async function encryptBytes(roomKey, bytes) {
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, roomKey, bytes);
            return { iv: b64encode(iv), cipher };
        }

        async function decryptBytes(roomKey, ivB64, cipherBuffer) {
            const iv = b64decode(ivB64);
            return crypto.subtle.decrypt({ name: 'AES-GCM', iv }, roomKey, cipherBuffer);
        }

        function isEncrypted(content) {
            return typeof content === 'string' && content.startsWith('e2ee:v1:');
        }

        async function derivePasswordKey(password, saltBytes) {
            const base = await crypto.subtle.importKey(
                'raw',
                enc.encode(password),
                'PBKDF2',
                false,
                ['deriveKey']
            );
            return crypto.subtle.deriveKey(
                {
                    name: 'PBKDF2',
                    salt: saltBytes,
                    iterations: 210000,
                    hash: 'SHA-256',
                },
                base,
                { name: 'AES-GCM', length: 256 },
                false,
                ['encrypt', 'decrypt']
            );
        }

        async function wrapPrivateKeyWithPassword(privateJwk, password) {
            const salt = crypto.getRandomValues(new Uint8Array(16));
            const iv = crypto.getRandomValues(new Uint8Array(12));
            const key = await derivePasswordKey(password, salt);
            const plain = enc.encode(typeof privateJwk === 'string' ? privateJwk : JSON.stringify(privateJwk));
            const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, plain);
            return JSON.stringify({
                version: 1,
                kdf: 'PBKDF2-SHA256-210000',
                salt: b64encode(salt),
                iv: b64encode(iv),
                ciphertext: b64encode(cipher),
            });
        }

        async function unwrapPrivateKeyWithPassword(backupJson, password) {
            const backup = typeof backupJson === 'string' ? JSON.parse(backupJson) : backupJson;
            if (!backup?.salt || !backup?.iv || !backup?.ciphertext) {
                throw new Error('Invalid account key backup.');
            }
            const salt = b64decode(backup.salt);
            const iv = b64decode(backup.iv);
            const cipher = b64decode(backup.ciphertext);
            const key = await derivePasswordKey(password, salt);
            const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, cipher);
            return JSON.parse(dec.decode(plain));
        }

        async function restoreFromAccountBackup(userId, password, backup) {
            if (!backup || !password) return false;
            const jwk = await unwrapPrivateKeyWithPassword(backup, password);
            if (!jwk || jwk.kty !== 'EC' || !jwk.d) {
                throw new Error('Account key backup is corrupted.');
            }
            localStorage.setItem(storageKey(userId), JSON.stringify(jwk));
            return true;
        }

        async function uploadAccountBackup(userId, password, backupUrl) {
            const raw = localStorage.getItem(storageKey(userId));
            if (!raw || !password || !backupUrl) return false;
            const backup = await wrapPrivateKeyWithPassword(raw, password);
            const res = await fetch(backupUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ backup }),
            });
            return res.ok;
        }

        /**
         * After login: restore private key from account backup, or upload local key as backup.
         */
        async function syncAccountIdentity(userId, password, { backupUrl, publicKeyUrl, backup }) {
            if (!userId || !password) return { restored: false, uploaded: false };

            let remoteBackup = backup;
            if (remoteBackup === undefined && backupUrl) {
                try {
                    const res = await fetch(backupUrl, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (res.ok) {
                        const data = await res.json();
                        remoteBackup = data.backup || null;
                    }
                } catch (e) {
                    remoteBackup = null;
                }
            }

            const local = localStorage.getItem(storageKey(userId));
            let restored = false;
            let uploaded = false;

            if (remoteBackup) {
                try {
                    await restoreFromAccountBackup(userId, password, remoteBackup);
                    restored = true;
                } catch (e) {
                    console.warn('Could not unlock account E2EE backup', e);
                    // Wrong password material or corrupt — keep local if present.
                }
            }

            if (publicKeyUrl) {
                await ensureIdentity(userId, publicKeyUrl);
            }

            if (backupUrl && localStorage.getItem(storageKey(userId))) {
                // Refresh server backup from the active local key (covers first device + re-mint).
                uploaded = await uploadAccountBackup(userId, password, backupUrl);
            }

            return { restored, uploaded };
        }

        return {
            ensureIdentity,
            generateRoomKey,
            exportRoomKeyRaw,
            importRoomKeyRaw,
            wrapRoomKeyFor,
            unwrapRoomKey,
            cacheRoomKey,
            loadCachedRoomKey,
            clearCachedRoomKey,
            wrapPrivateKeyWithPassword,
            unwrapPrivateKeyWithPassword,
            restoreFromAccountBackup,
            uploadAccountBackup,
            syncAccountIdentity,
            encryptText,
            decryptText,
            encryptBytes,
            decryptBytes,
            isEncrypted,
            publicFingerprint,
            storageKey,
            b64encode,
            b64decode,
        };
    })();
</script>
@endverbatim
