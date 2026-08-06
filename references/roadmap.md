# Technical Roadmap

> User journeys: [user-flows.md](./user-flows.md)  
> Architecture rules: [architecture.md](./architecture.md)

## Phase status

| Phase | Status |
|-------|--------|
| 1 — Migrations + models | ✅ Complete |
| 2 — Core services | ✅ Complete |
| 2.5 — Web monolith refactor | ✅ Complete |
| 3 — Authorization (policies) | ✅ Complete |
| 4 — Blade UI | ✅ Complete (MVP) |
| 5 — Realtime messaging | ✅ Complete (poll + broadcast events) |
| 6 — Frontend polish | ✅ Complete (breadcrumbs, closed tenant, owner controls) |

## Phase 5 — Realtime (implemented)

- `MessageSent` event (`ShouldBroadcastNow`) on private channel `chat.{type}.{id}`
- Poll endpoint: `GET /messages/{chatType}/{chatId}/poll?after={id}`
- Alpine.js client: 3s polling + AJAX send on chat and thread views
- `config/broadcasting.php` + `routes/channels.php` ready for Reverb/Pusher upgrade
- Default: `BROADCAST_CONNECTION=log` (events logged; client uses polling)

## Phase 6 — Polish (implemented)

- Breadcrumbs partial on groups, chat, thread
- Closed tenant blocked on login, register, and `IdentifyTenant` middleware
- Owner dashboard: close / reopen workspace actions
- Responsive chat compose (stacked on mobile)
- `Tenant.closed_by_id` mass-assignable for owner actions

## SOW checklist

| # | Criterion | Status |
|---|-----------|--------|
| 1 | Groups with custom roles | ✅ |
| 2 | Member add/assign with policies | ✅ |
| 3 | Real-time messages | ✅ (poll-based; WebSocket-ready) |
| 4 | Duos (two-user, group-scoped) | ✅ |
| 5 | Files / threads UI | ✅ (multi-file, typed previews, thread uploads) |
| 6 | Two-group merge sessions | ✅ |
| 7 | Tagging | ✅ (`@all` / `@selected` / `@role` / `@user` / merge tags) |
| 8 | No private messaging outside structures | ✅ |
| 9 | Responsive pass | ✅ (core layouts) |
| 10 | Clean layered architecture | ✅ |

## Post-MVP (see [todo.md](./todo.md))

- Video call & voice call (WebRTC; header opens placeholder modal today)
- Laravel Reverb or Ratchet/Pawl WebSocket server (replace polling client)
- E2EE for messages and attachments
- Expanded feature test coverage
- Replay commits onto individual `feature/*` branches

## Execution order (done)

1. Docs + typo cleanup + stale reference removal
2. Invitation / owner invariant audit
3. Route / controller consistency
4. Messaging + file/thread UX
5. Blade UI consolidation
6. Realtime (poll + broadcast infrastructure)
7. Responsive polish + closed tenant enforcement
