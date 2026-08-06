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
            const keyName = storageKey(userId);
            let privateJwk = localStorage.getItem(keyName);
            let keyPair;

            if (privateJwk) {
                const priv = await importPrivateJwk(privateJwk);
                const pubJwk = JSON.parse(privateJwk);
                delete pubJwk.d;
                delete pubJwk.key_ops;
                delete pubJwk.ext;
                pubJwk.key_ops = [];
                const pub = await importPublicJwk(pubJwk);
                keyPair = { privateKey: priv, publicKey: pub };
            } else {
                keyPair = await generateIdentity();
                privateJwk = JSON.stringify(await exportPrivateJwk(keyPair.privateKey));
                localStorage.setItem(keyName, privateJwk);
            }

            const publicJwk = JSON.stringify(await exportPublicJwk(keyPair.publicKey));
            await fetch(publicKeyUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ public_key: publicJwk }),
            });

            return keyPair;
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

        return {
            ensureIdentity,
            generateRoomKey,
            wrapRoomKeyFor,
            unwrapRoomKey,
            encryptText,
            decryptText,
            encryptBytes,
            decryptBytes,
            isEncrypted,
            b64encode,
            b64decode,
        };
    })();
</script>
@endverbatim
