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
| 4 — Blade UI | 🔄 In progress |
| 5 — Realtime (WebSocket) | ⬜ Not started |
| 6 — Frontend polish | ⬜ Not started |

## Phase 4 — remaining work

- [ ] Navigation coherence (sidebar links, breadcrumbs)
- [ ] Empty states consistency
- [ ] Invitation views: correct admin vs member messaging
- [ ] Chat / file / thread UX pass
- [ ] Route helper audit
- [ ] Ban management UI (model fields exist)

## MVP blockers

1. **Invitation hardening** — end-to-end owner admin + tenant member flows
2. **Single-owner invariant** — no path creates a second owner on tenant 1
3. **File + thread UX** — discoverability from upload through thread view
4. **Message flow consistency** — group / duo / merge session parity
5. **UI consistency** — forms, flash, empty states

## SOW checklist

| # | Criterion | Status |
|---|-----------|--------|
| 1 | Groups with custom roles | ✅ |
| 2 | Member add/assign with policies | ✅ |
| 3 | Real-time messages | Phase 5 |
| 4 | Duos (two-user, group-scoped) | ✅ |
| 5 | Files / threads UI | 🔄 UX pass |
| 6 | Two-group merge sessions | ✅ |
| 7 | Tagging | Deferred |
| 8 | No private messaging outside structures | ✅ |
| 9 | Responsive pass | Phase 6 |
| 10 | Clean layered architecture | ✅ |

## Execution order

1. Docs + typo cleanup + stale reference removal
2. Invitation / owner invariant audit
3. Route / controller consistency
4. Messaging + file/thread UX
5. Blade UI consolidation
6. Realtime (Ratchet/Pawl + Redis pub/sub)
7. Responsive polish

## Phase 5 preview

- WebSocket server (Ratchet/Pawl)
- Channels: `chat.{id}.{type}`
- Redis pub/sub broadcast
- Blade client integration

## Phase 6 preview

- Tailwind/Alpine responsive cleanup
- Sidebar / chat / members panel
- Merge session status indicators
- Mobile usability
