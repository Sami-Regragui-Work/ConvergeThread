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

Project docs live in `references/` (gitignored, local only): architecture, roadmap, user flows, ERD, and branching rules. Start with `references/README.md`.

Scope documents in the same folder: the two `.docx` files (full project + MVP SOW).

## Branching

Feature work uses `feature/*` branches merged into `main`. Branch rules are in `references/branching.md` (local).
