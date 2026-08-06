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
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

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
- Poll-based chat updates (`BROADCAST_CONNECTION=log` by default)

## Documentation

Project docs live in `references/` (tracked on git branch `docs/project-documentation`; gitignored on `main` for local use). Start with `references/README.md` and `references/todo.md`.

Scope documents: the two `.docx` files in `references/` (full project + MVP SOW).

## Branching

Feature work uses `feature/*` branches merged into `main`. See `references/branching.md` on the docs branch.

## Roadmap / Todo

- **E2EE (end-to-end encryption)** for messages and attachments
- WebSocket real-time sync (replacing poll-based updates)
- Voice and video calls in chat header
- Full hierarchy UI polish and cross-hierarchy rules
