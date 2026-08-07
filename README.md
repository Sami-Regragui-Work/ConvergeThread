# ConvergeThread

Laravel 12 collaboration platform — session-auth web monolith with groups, duos, merge sessions, and threaded messaging.

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
- Markdown compose mode, media trim/speed before send, in-app media viewer
- Header chat search (local encrypted IndexedDB index; per-chat or all my chats) and files-by-thread browse
- Alpine modals for group create/rename, duo create, and merge-session create (classic pages remain as fallback)

## Documentation

Project docs live in `references/` (tracked on git branch `docs/project-documentation`; gitignored on `main` for local use). Start with `references/README.md` and `references/todo.md`.

E2EE / WebRTC caveats and how to fix them: `references/known-limitations.md` on the docs branch.

Scope documents: the two `.docx` files in `references/` (full project + MVP SOW).

## Branching

Feature work uses `feature/*` branches merged into `main`. See `references/branching.md` on the docs branch.

## Roadmap / Todo

See `references/todo.md` and `references/known-limitations.md` (docs branch). Remaining highlights:

- App-level call E2EE if using SFU (Insertable Streams / SFrame)
- Dense editors still on full pages (tenant roles, hierarchies, role-override permissions)
- Sidebar UX polish on very small screens
