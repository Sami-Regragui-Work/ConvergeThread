# ConvergeThread Documentation

This folder is the **working source of truth** for development.

## Documents

| File | Purpose |
|------|---------|
| [architecture.md](./architecture.md) | Stack, rules, layers, routes, invariants |
| [roadmap.md](./roadmap.md) | Phases, SOW checklist, execution order |
| [todo.md](./todo.md) | Done vs pending backlog (big-ticket items) |
| [known-limitations.md](./known-limitations.md) | E2EE / WebRTC caveats and how to fix them |
| [user-flows.md](./user-flows.md) | End-user journeys |
| [erd.dbml](./erd.dbml) | Database schema (dbdiagram syntax) |
| [branching.md](./branching.md) | Git branch discipline |

## Ground truth (scope)

- `Full_Project_functional_scope(1)(1).docx` — full product intent
- `S.O.W_fil_rouge_Sami_Regragui_-MVP(1)(1).docx` — MVP contract

## Conventions

- Laravel 12 web monolith: session auth, Blade, redirects, flash messages
- PHP 8.5 target
- Controllers stay thin; services own domain logic; policies authorize
- After Form Request validation: `$credentials = $request->validated()` then use `$credentials['field']`

When docs conflict with code, **code wins** — update these files to match.

## Git

Tracked on branch **`docs/project-documentation`** only. On `main`, `references/` is gitignored so local copies stay private alongside the `.docx` files.
