# Known limitations & how to fix them

Operational caveats for E2EE and WebRTC as shipped. Pair with [todo.md](./todo.md).  
**Out of scope:** migrating pre-E2EE plaintext history — we assume a fresh DB / `migrate:fresh`.

---

## 1. Calls fail on restrictive NATs (STUN only)

**Today:** `RTCPeerConnection` uses public Google STUN only (`stun.l.google.com`). Peers behind symmetric NAT / strict firewalls often never get media.

**Fix:**
1. Run a TURN server (coturn) or use a hosted TURN (Twilio, Cloudflare Calls, Metered, etc.).
2. Put credentials in `.env`, e.g. `TURN_URLS`, `TURN_USERNAME`, `TURN_CREDENTIAL`.
3. Expose an authenticated endpoint (or Blade config) that returns `iceServers` including TURN.
4. In `chat-panel-script.blade.php`, replace the hardcoded STUN list with that config:

```js
iceServers: [
  { urls: 'stun:stun.l.google.com:19302' },
  { urls: 'turn:turn.example.com:3478', username: '...', credential: '...' },
]
```

Prefer short-lived TURN credentials minted server-side.

---

## 2. Calls need Reverb (no signaling without WebSockets)

**Today:** Offer/answer/ICE go through `CallSignal` on the private chat channel. Without Reverb, invite never reaches the other client (polling does not carry call signals).

**Fix:** Always run `composer run serve` (or `reverb:start`) locally. In production set `BROADCAST_CONNECTION=reverb` and keep the Reverb process up. Optional hardening: queue a fallback “missed call” notification if no `join` arrives within N seconds.

---

## 3. getUserMedia needs a secure context

**Today:** Mic/camera require HTTPS (or `http://localhost` / `127.0.0.1`). Plain HTTP on a LAN IP will fail.

**Fix:** Local: stick to `127.0.0.1:8000`. Deploy: terminate TLS (Caddy/nginx/Cloudflare). Document this for demos on other devices.

---

## 4. Mesh calls do not scale

**Today:** Every joiner opens a peer connection to every other joiner (full mesh). Fine for 2–4 people; bandwidth and CPU explode after that.

**Fix:** Introduce an SFU (LiveKit, mediasoup, Janus, Cloudflare Calls). Clients publish one uplink; the SFU fans out. Keep Reverb (or SFU signaling) for session control; move media off mesh. Update the call UI to talk to the SFU client SDK instead of raw `RTCPeerConnection` mesh helpers.

---

## 5. Thread view has no call UI

**Today:** Voice/video buttons and call modals live on `messages/index.blade.php` only.

**Fix:** Pass `callSignalUrl` + `currentUserName` into the thread `chatPanel(...)` config and reuse the same header controls / modals (or extract a Blade partial). Signaling already keys off `chatType`/`chatId`, so the parent chat channel works if you intentionally call from the main chat; for “call about this thread” you can keep using the parent chat id.

---

## 6. E2EE private keys are browser-local only

**Today:** Identity private key is stored in `localStorage` (`ct_e2ee_private_{userId}`). A second browser/device generates a **new** keypair, posts a new public key, and cannot unwrap old room-key shares meant for the previous public key. Clearing site data = permanent loss of that identity.

**Fix (pick one or combine):**
1. **Recovery passphrase:** encrypt the private JWK with a key derived from a user passphrase (PBKDF2/Argon2 + AES-GCM); upload the wrapped blob to the server; restore on new devices.
2. **Device linking:** QR/code challenge — old device wraps the private key (or each room key) to the new device’s ephemeral public key.
3. **Per-device keys + re-share:** keep multiple public keys per user; when a device comes online, an existing device (or the sender) wraps the room key to every device public key (`chat_key_shares` already is per-user; extend to per-device).

Until then: one browser profile per account for demos; warn users that clearing storage loses history decryptability.

---

## 7. Late joiners wait for a room-key share

**Today:** If the chat already has a room key and the current user has no `chat_key_shares` row, the UI shows “Waiting for an existing member to share…” until someone with the key opens the chat and `publishShares()` runs.

**Fix:**
1. On membership add / invite accept, have any online member (or the inviter) push shares immediately via Reverb (`WorkspaceUpdated` / a dedicated `ChatKeyShare` event).
2. Or: sender-side fan-out — each message encrypts a small per-recipient key package (heavier; closer to Signal sender keys).
3. UX: allow the waiting user to ping “request key” so online members re-run share publish.

---

## 8. Notification / search previews stay opaque

**Today:** Push/in-app previews for encrypted messages are generic (“Encrypted message”). Server-side search cannot read ciphertext.

**Fix:** Accept as E2EE tradeoff, or add **client-side** search index (encrypted local DB). Do not decrypt on the server if the goal is true E2EE.

---

## 9. Call media is not app-level E2EE

**Today:** Browser WebRTC uses DTLS-SRTP between peers (transport encryption). There is no extra Insertable Streams / SFrame layer; an SFU in the middle could see media unless you add end-to-end media crypto.

**Fix:** If you move to an SFU and need E2EE calls, use Insertable Streams / SFrame (or LiveKit E2EE) so the SFU relays opaque frames. Mesh STUN/TURN-only deployments already keep media peer-to-peer.

---

## 10. Sidebar UX polish

**Today:** Desktop sidebar toggle defaults can be awkward on very small screens.

**Fix:** Tune Alpine/CSS breakpoints in `layouts/app.blade.php` (default collapsed under `md`, remember preference in `localStorage`).

---

## Quick reference

| Caveat | Code touchpoints |
|--------|------------------|
| TURN | `chat-panel-script` `iceServers`; new env + optional controller |
| Reverb for calls | `CallSignal`, `composer run serve`, `.env` broadcasting |
| Secure context | Deploy TLS; demo on localhost |
| Mesh → SFU | Replace mesh helpers; keep `CallController` or SFU webhook |
| Thread calls | `messages/thread.blade.php` + shared partial |
| Multi-device E2EE | `chat-crypto.blade.php` `localStorage`; new recovery/share APIs |
| Late key share | `ChatCryptoController` + membership hooks + broadcast |
| Opaque previews | Notification classes; intentional |
| Call E2EE | Insertable Streams if SFU |
| Sidebar | `layouts/app.blade.php` |
