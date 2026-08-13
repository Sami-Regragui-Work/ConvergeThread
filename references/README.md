# Project References

This folder holds the working references for ConvergeThread. They are **not**
committed on `main` (gitignored locally); a full updated copy is tracked on the
`docs/project-documentation` branch.

## Scope documents (archived)

The original scope documents were archived **outside the project** on
2026-08-13 (binary `.docx` cannot be read in-repo, and they were stale):

- `/home/frogsam/D/YouCode/Fil_Rouge/docs-archive/Full_Project_functional_scope(1)(1).docx` — full functional scope
- `/home/frogsam/D/YouCode/Fil_Rouge/docs-archive/S.O.W_fil_rouge_Sami_Regragui_-MVP(1)(1).docx` — Statement of Work (MVP contract)

If they are needed again, the archive path above is the canonical copy.

## Contents

- `AGENTS.md` — persistent project rules (session entry point; formerly at repo root)
- `LIVE_LOG.md` — current session's thinking/actions (start here mid-session)
- `context.md` — fresh-session entry point
- `todo.md` — triaged backlog + confirmed bugs
- `architecture.md`, `roadmap.md`, `known-limitations.md`, `user-flows.md`, `erd.dbml` — supporting docs
- `SESSION_NOTES_2026-08-11.md` — older session notes

## Suggested reading order

1. `context.md`
2. `todo.md` → `LIVE_LOG.md`
3. `architecture.md` → `roadmap.md` → `known-limitations.md` → `user-flows.md`

## Git note

`main` carries **no working references** — only this folder's `README.md` is
committed there; the rest live on `dev` (local) and `docs/project-documentation`
(canonical shared copy).
