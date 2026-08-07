# Known limitations & how to fix them

Operational caveats for E2EE and WebRTC as shipped. Pair with [todo.md](./todo.md).  
**Out of scope:** migrating pre-E2EE plaintext history — we assume a fresh DB / `migrate:fresh`.

---

## 1. Calls fail on restrictive NATs (without TURN)

**Today:** `config/webrtc.php` always includes public Google STUN. Optional TURN is wired from `TURN_URLS` / `TURN_USERNAME` / `TURN_CREDENTIAL` into `iceServers` for mesh calls. Without TURN, peers behind symmetric NAT / strict firewalls often never get media.

**Fix:** Run coturn or a hosted TURN; set the env vars; reload config. Short-lived TURN credentials minted server-side are nicer for production.

---

## 2. Calls need Reverb (no signaling without WebSockets)

**Today:** Offer/answer/ICE (mesh) and invite/join/leave (mesh + SFU) go through `CallSignal` on the private chat channel. Without Reverb, invite never reaches the other client (polling does not carry call signals).

**Fix:** Always run `composer run serve` (or `reverb:start`) locally. In production set `BROADCAST_CONNECTION=reverb` and keep the Reverb process up. Optional hardening: queue a fallback “missed call” notification if no `join` arrives within N seconds.

---

## 3. getUserMedia needs a secure context

**Today:** Mic/camera require HTTPS (or `http://localhost` / `127.0.0.1`). Plain HTTP on a LAN IP will fail.

**Fix:** Local: stick to `127.0.0.1:8000`. Deploy: terminate TLS (Caddy/nginx/Cloudflare). Document this for demos on other devices.

---

## 4. Mesh vs LiveKit SFU

**Today:** Duo calls stay on full-mesh (+ TURN when configured). Group/merge calls use LiveKit SFU when `LIVEKIT_URL` + `LIVEKIT_API_KEY` + `LIVEKIT_API_SECRET` are set (`CallController` mints JWTs; client loads `livekit-client` from CDN). Without LiveKit, group calls fall back to mesh. `LIVEKIT_FORCE_ALL=true` forces SFU for every chat type.

**Ops:** Run LiveKit (e.g. `livekit/livekit-server --dev`) and point `.env` at it. See README.

**Still open:** App-level call E2EE on the SFU path (see §9).

---

## 5. Thread view call UI

**Today:** Thread view reuses the shared call buttons/modals and parent-chat signaling (`chatType`/`chatId` of the parent chatable).

---

## 6. E2EE private keys are browser-local only

**Today:** Identity private key is stored in `localStorage` (`ct_e2ee_private_{userId}`), with optional password-wrapped account backup restore on login. A second browser without the backup passphrase generates a **new** keypair and cannot unwrap old room-key shares. Clearing site data without a backup = permanent loss of that identity.

**Fix (pick one or combine):**
1. **Recovery passphrase** (shipped): encrypt the private JWK; upload wrapped blob; restore on new devices.
2. **Device linking:** QR/code challenge — old device wraps the private key (or each room key) to the new device’s ephemeral public key.
3. **Per-device keys + re-share:** keep multiple public keys per user; when a device comes online, an existing device (or the sender) wraps the room key to every device public key.

Until then: one browser profile per account for demos unless backup/restore is used; warn users that clearing storage loses history decryptability without a backup.

---

## 7. Late joiners wait for a room-key share

**Today:** If the chat already has a room key and the current user has no `chat_key_shares` row, the UI shows “Waiting for an existing member to share…” until someone with the key opens the chat and `publishShares()` runs. A “request key” path exists for online members to re-share.

**Fix:**
1. On membership add / invite accept, have any online member (or the inviter) push shares immediately via Reverb.
2. Or: sender-side fan-out — each message encrypts a small per-recipient key package (heavier; closer to Signal sender keys).

---

## 8. Notification previews stay opaque (search does not)

**Today:** In-app notifications still show generic “Encrypted message” text — the server never sees plaintext. **Chat search is different:** header Search syncs a ciphertext feed (`messages.search-feed`), decrypts with the room key in the browser, and stores searchable plaintext in IndexedDB (`ChatSearchIndex`) — Proton-style body keywords without server-side decrypt. “All my chats” fans out local sync.

**Fix (notifications):** Accept as E2EE tradeoff, or add client-only notification preview cache after decrypt. Do not decrypt on the server.

**Harden (search index):** Wrap IndexedDB rows with AES-GCM using a key derived from the identity private key (or a user passphrase) so a stolen disk dump of IndexedDB is not readable plaintext. A new browser must re-sync/decrypt the feed (same as multi-device E2EE).

---

## 9. Call media is not app-level E2EE on SFU

**Today:** Browser WebRTC uses DTLS-SRTP between peers (transport encryption) on mesh. LiveKit SFU can observe media unless you add Insertable Streams / SFrame / LiveKit E2EE.

**Fix:** Enable LiveKit E2EE or Insertable Streams so the SFU relays opaque frames. Mesh STUN/TURN-only deployments already keep media peer-to-peer.

---

## 10. Sidebar UX polish

**Today:** Desktop sidebar toggle defaults can be awkward on very small screens.

**Fix:** Tune Alpine/CSS breakpoints in `layouts/app.blade.php` (default collapsed under `md`, remember preference in `localStorage`).

---

## 11. Dense editors stay on full pages

**Today:** Group create/rename, duo create, and merge-session create use Alpine modals. Tenant roles, hierarchies, and role-override permission pickers remain full pages (intentionally denser).

---

## Quick reference

| Caveat | Code touchpoints |
|--------|------------------|
| TURN | `config/webrtc.php`, `.env` `TURN_*`, `iceServers` in chat panel |
| Reverb | `composer run serve`, `CallSignal`, Echo channels |
| Secure context | Browser `isSecureContext`; HTTPS in prod |
| Mesh → SFU | `LiveKitTokenService`, `CallController::sfuToken`, `chat-panel-script` SFU branch |
| Thread calls | `messages/thread.blade.php` + `chat-call-ui` |
| Multi-device E2EE | `chat-crypto.blade.php` `localStorage` + backup APIs |
| Late key share | `ChatCryptoController` + membership hooks + broadcast |
| Opaque notif previews | Notification classes; intentional |
| Body search | `ChatSearchIndex`, `ChatBrowseController`, `chat-browse-ui` |
| Encrypt search IDB | `chat-search-index.blade.php` |
| Call E2EE | Insertable Streams / LiveKit E2EE if SFU |
| Sidebar | `layouts/app.blade.php` |
| Create modals | `group-name-modal`, `duo-create-modal`, `merge-session-modal` |
