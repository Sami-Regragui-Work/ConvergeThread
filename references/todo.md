# ConvergeThread — TODO

## Done

### MVP platform
- Multi-tenant workspaces, system roles (Admin / Moderator / Member), custom tenant roles
- Groups, members, invitations (workspace + group), role assignment on invite
- Group role overrides, duos, two-group merge sessions
- Threaded messaging, file attachments on main chat, Reverb WebSocket realtime (poll fallback)
- Owner dashboard (tenants, users, ban, close workspace)
- Session auth, policies, closed-tenant enforcement
- Breadcrumbs, flash with copyable links, global back navigation
- Responsive layouts (owner dashboard, chat compose, invite forms)
- Attachment download route, 403 go-back

### Merge sessions — workspace isolation
- List and access merge sessions only within the current workspace
- Chat access limited to active members of the two merged groups

### Notifications & mutes
- In-app notification center + unread badge
- Mentions (`@all`, `@selected`, `@role:`, `@username`, merge `@group:` / `@group.user`)
- Chat / thread mute toggles
- Stacked non-mention chat notifications

### Workspace members & hierarchies
- Workspace members page (view for all; role manage for permitted users)
- Role hierarchy chains with level membership sync
- Tenant role colors (picker / hex) used in chat names and mentions

### Chat UX polish
- Full-height chat layout (`fill-height`)
- Multi-file attachments in one message (compose append across picks)
- Typed file cards (PDF/PPT/docs) with size labels; image + video previews
- Inline message edit for own messages
- Confirm popups (replace browser `confirm`)
- Mention pills: `@display name` with rounded highlight
- Keep focus in composer after Enter/send
- Header shows tenant name (app branding stays in sidebar)

### Thread reply attachments
- File uploads on thread replies (same compose path as main chat)

### Invitation management UI
- Pending invitations list + revoke

### Realtime & owner UX
- Laravel Reverb broadcasts for chat messages, workspace list sync, and call signaling
- Owner dashboard live client-side search (no Enter submit)
- Display-name capitalization helper (first letter only)

### Mention UX & duo sync
- Mention pills: no nested/false highlights; darker purple style
- Mention menu: arrow/tab selection; @ button insert works without typing @
- Duo create/delete bumps owner/workspace live sync
- `composer run serve` starts HTTP + Reverb together

### E2EE chat
- Client-side Web Crypto E2EE for message text and attachments
- Per-user ECDH identity keys (private key in browser) + per-chat AES room keys
- Server stores ciphertext only; notifications show generic encrypted previews
- Fresh DB assumed — no plaintext→ciphertext migration

### Voice & video calls
- WebRTC voice/video in chat (invite / join / offer / answer / ICE via Reverb)
- Mesh between joined peers; mute / camera toggles; Google public STUN

### Chat search & media browse
- Header Search + Files (non-owner)
- Proton-style body keyword search: server serves ciphertext feed; client decrypts into IndexedDB and queries locally
- Filters: author id, has-file, date range
- Files panel grouped by root thread (root + reply attachments); Go to chat deep-link (`?message=`)
- Deep-link scroll/highlight; replies redirect to thread view

---

## Todo

How to fix each item: [known-limitations.md](./known-limitations.md).

### TURN for restrictive NATs
Calls use public STUN only; symmetric NAT / strict firewalls may get signaling but no media.

### Calls require Reverb
Call invite/SDP/ICE are WebSocket-only; polling does not carry call signals. Production must keep Reverb up.

### Secure context for getUserMedia
Mic/camera need HTTPS (or localhost). LAN `http://192.168…` demos will fail without TLS.

### Mesh call scaling
Full-mesh peer connections; fine for small chats, not for large group calls — needs an SFU later.

### Call UI on thread view
Voice/video controls exist on main chat only; thread view has no call entry point.

### Multi-device E2EE / recovery
Identity private key lives in `localStorage` only — second device or cleared site data cannot decrypt history without recovery or device linking. Local search index is per-browser too.

### Late joiner room-key share
New members wait until an existing member with the room key opens the chat (or shares keys); no proactive share-on-invite yet.

### Opaque notification previews
In-app notifications still cannot show plaintext bodies (server never sees them). Chat search uses a local decrypted index instead.

### Encrypt local search index at rest
IndexedDB currently stores decrypted plaintext for speed (Proton encrypts the local index). Harden by wrapping index rows with a key derived from the identity private key / passphrase.

### Cross-chat search in one query
Search UI is per selected chat today; extend to fan-out sync + query across all indexed chats.

### Convert simple edit pages to modals
With live workspace sync, group rename and similar flows can be header/sidebar modals instead of full pages.

### App-level call E2EE (if SFU)
DTLS-SRTP protects peer-to-peer media today; an SFU path would need Insertable Streams / SFrame for true E2EE calls.

### Sidebar UX
Further polish for desktop toggle defaults on very small screens if needed.
