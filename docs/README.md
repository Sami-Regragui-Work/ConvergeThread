# ConvergeThread Documentation

This folder is the **working source of truth** for development. It replaces the scattered `.txt` notes in `references/` (except the two `.docx` scope documents).

## Documents

| File | Purpose |
|------|---------|
| [architecture.md](./architecture.md) | Stack, rules, layers, routes, invariants |
| [roadmap.md](./roadmap.md) | Phases, blockers, execution order |
| [user-flows.md](./user-flows.md) | End-user journeys |
| [erd.dbml](./erd.dbml) | Database schema (dbdiagram syntax) |
| [branching.md](./branching.md) | Git branch discipline |

## Ground truth (outside this folder)

Keep in `references/`:

- `Full_Project_functional_scope(1)(1).docx` — full product intent
- `S.O.W_fil_rouge_Sami_Regragui_-MVP(1)(1).docx` — MVP contract

## Conventions

- Laravel 12 web monolith: session auth, Blade, redirects, flash messages
- PHP 8.5 target
- Controllers stay thin; services own domain logic; policies authorize
- After Form Request validation: `$credentials = $request->validated()` then use `$credentials['field']`

When docs conflict with code, **code wins** — update these files to match.
