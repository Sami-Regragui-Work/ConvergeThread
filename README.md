# ConvergeThread

Work-oriented multi-group collaboration — not another general-purpose messenger.

Laravel 12 web monolith (session auth, Blade, MySQL) with isolated workspaces, hierarchical roles, groups, duos, temporary inter-group merge sessions, and threaded messaging.

## Why ConvergeThread (not Slack / Teams / Discord / WhatsApp)

Classic chat apps optimize for open conversation. Organizations need something else:

| Pain with typical messengers | What ConvergeThread does |
|------------------------------|---------------------------|
| **Digital distraction** — feeds and DMs pull focus away from work | Chats are scoped to **groups, duos, and merge sessions** — no free-for-all DM graph |
| **Internal silos** — departments cannot collaborate without a messy side channel | **Merge sessions** open a temporary shared channel between exactly two groups, then close |
| **Unwanted contact** — hierarchy and org charts get abused as open ping targets | Access is **permissioned** (tenant → group → member override), not “everyone can DM everyone” |
| **Scattered threads & files** — context lives in email, Drive, and three chat tools | **Threaded messages + attachments** stay on the chatable (group / duo / merge) |
| **Weak role/access control** — coarse admin/member toggles | **Three-level permissions** with custom tenant roles, group role overrides, and JSON permission catalogs |

### Our answer (product pillars)

1. **Structure** — Isolated tenants (workspaces), users, and hierarchical roles (including role hierarchy chains).
2. **Collaborate** — Groups, membership lifecycle, and **duos** (two-person channels under a parent group).
3. **Converge** — **Merge sessions**: temporary inter-group messaging with shared history for the session.
4. **Trace** — Polymorphic messages, attachments, threads, edits/deletes, and audit-friendly server storage (ciphertext when E2EE is on).

Built for teams that need **controlled collaboration**, not another social inbox.

## What shipped beyond the PFE MVP pitch

The presentation MVP covered auth, groups, members/roles, messaging, duos, merge sessions, and Laravel policies. Since then the product also includes:

- **Client-side E2EE** for message text and attachments (Web Crypto; server stores ciphertext; password-wrapped key backup)
- **Voice/video calls** — WebRTC mesh for small/duo rooms; optional **LiveKit SFU** for larger group/merge calls; optional call media E2EE via Insertable Streams
- **Mentions & notifications** — `@user` / `@all` / `@role:` / merge `@group:`; in-app notification center; chat & thread mutes
- **Workspace tooling** — members page, role hierarchy UI, tenant role colors, invitation list/revoke, owner dashboard live search
- **Rich composer** — Markdown (CommonMark + GFM, spoilers, alerts, footnotes, safe HTML), media trim/speed before send, fence **Monaco** editor (desktop; sleeps when you leave the fence), lightweight suggestions on mobile
- **Search & files** — local decrypted IndexedDB search; files-by-thread browse; deep-link to message
- **Attachment viewer** — images/video/audio/PDF; markdown/text/code preview; DOCX via Mammoth; download fallback for other Office types
- **UX polish** — Alpine create/edit modals, responsive sidebar drawer, Reverb realtime with poll fallback

## Requirements

- PHP 8.2+ (8.5 recommended)
- Composer
- MySQL 8+ (or MariaDB)
- Optional: Node.js 20+ (only if you use Vite assets; the MVP UI loads Tailwind and Alpine from CDN)

## Install

```bash
git clone <repository-url> ConvergeThread
cd ConvergeThread
composer install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=converge_thread
DB_USERNAME=root
DB_PASSWORD=your_password
```

Create the database, then migrate and seed:

```bash
# create DB once, e.g. mysql -e "CREATE DATABASE converge_thread CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed
php artisan storage:link
```

## Run

```bash
# HTTP + WebSockets (local realtime)
composer run serve
```

That starts `php artisan serve` and `php artisan reverb:start` together. Stopping the command stops both.

Full local stack (also queue, logs, Vite):

```bash
composer run dev
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

`.env.example` defaults to `BROADCAST_CONNECTION=reverb`. If Reverb is not running, the UI falls back to polling (chat still works; **live calls need Reverb**). Mic/camera need **HTTPS or localhost**.

Optional TURN (restrictive NATs): set `TURN_URLS`, `TURN_USERNAME`, `TURN_CREDENTIAL` in `.env` (see `config/webrtc.php`).

Optional LiveKit SFU (large group/merge calls): set `LIVEKIT_URL`, `LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET`. Duos stay on mesh unless `LIVEKIT_FORCE_ALL=true`. Local demo:

```bash
docker run --rm -p 7880:7880 \
  -e LIVEKIT_KEYS="devkey: secret" \
  livekit/livekit-server --dev
# then LIVEKIT_URL=ws://127.0.0.1:7880 LIVEKIT_API_KEY=devkey LIVEKIT_API_SECRET=secret
```

After seeding, sign in with the owner account from `database/seeders/Permanents/OwnerSeeder.php` (check that file for the seeded email/password), or register into the default workspace tenant.

For message attachments, ensure `storage/app/public` is writable and the `public/storage` link exists (`php artisan storage:link`).

## Tests

```bash
php artisan test
```

## Stack

- PHP, Laravel 12
- Blade + Tailwind (CDN) + Alpine.js
- Session authentication (`web` guard)
- Laravel Reverb WebSockets for chat, workspace sync, and WebRTC call signaling
- Client-side E2EE for chat text and attachments (Web Crypto; private keys stay in the browser; password-wrapped account backup restores keys automatically on login)
- WebRTC voice/video calls (mesh + optional TURN for small/duo calls; optional LiveKit SFU for group/merge)
- Markdown compose mode (CommonMark + GFM + spoilers, alerts, footnotes, safe HTML); fence Monaco IntelliSense on desktop (lazy load / dispose); media trim/speed before send; in-app media viewer
- Header chat search (local encrypted IndexedDB index; per-chat or all my chats) and files-by-thread browse
- Alpine modals for group create/rename, duo create, merge-session create, tenant roles, hierarchy create, and role-override permissions (classic pages remain as fallback)
- LiveKit SFU call media E2EE via Insertable Streams (shared chat room key) when the browser supports it
- Responsive sidebar: mobile drawer with close control, desktop open/close preference persisted

## Documentation

Project docs live in `references/` (tracked on git branch `docs/project-documentation`; gitignored on `main` for local use). Start with `references/README.md` and `references/todo.md`.

E2EE / WebRTC caveats and how to fix them: `references/known-limitations.md` on the docs branch.

Scope documents: the two `.docx` files in `references/` (full project + MVP SOW).

## Branching

Feature work uses `feature/*` branches merged into `main`. See `references/branching.md` on the docs branch.

## Roadmap / Todo

See `references/todo.md` and `references/known-limitations.md` (docs branch).
