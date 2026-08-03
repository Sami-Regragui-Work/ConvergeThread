# ConvergeThread

Laravel 12 collaboration platform — session-auth web monolith with groups, duos, merge sessions, and threaded messaging.

## Stack

- PHP 8.5, Laravel 12
- Blade + Tailwind (CDN) + Alpine.js
- Session authentication (`web` guard)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Documentation

See [`docs/`](./docs/README.md) for architecture, roadmap, user flows, ERD, and branching rules.

Ground-truth scope documents live in `references/` (`.docx` files, gitignored locally).

## Branching

Feature work uses `feature/*` branches merged into `main`. See [docs/branching.md](./docs/branching.md).
