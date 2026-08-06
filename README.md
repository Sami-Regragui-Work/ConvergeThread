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

`.env.example` defaults to `BROADCAST_CONNECTION=reverb`. If Reverb is not running, the UI falls back to polling (chat still works; **live calls need Reverb**).

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
- Client-side E2EE for chat text and attachments (Web Crypto; private keys stay in the browser)
- WebRTC voice/video calls in chat (STUN; mesh between joined peers)

## Documentation

Project docs live in `references/` (tracked on git branch `docs/project-documentation`; gitignored on `main` for local use). Start with `references/README.md` and `references/todo.md`.

Scope documents: the two `.docx` files in `references/` (full project + MVP SOW).

## Branching

Feature work uses `feature/*` branches merged into `main`. See `references/branching.md` on the docs branch.

## Roadmap / Todo

- TURN servers for restrictive NATs (calls use public STUN today)
- Multi-device E2EE private-key sync / recovery
