# ConvergeThread — Live session log

> Working log for the current working session. `context.md` is the fresh-session
> entry point; this file records what was investigated / decided / done in this
> session. Keep it current while working, and fold the stable conclusions back
> into `context.md` / `todo.md` at the end of a session.

## Session 2026-08-13 — backlog triage & continuity tooling

### Objectives
- Audit the user's categorized list (done / partial / still-open) against the real code.
- Rewrite `references/todo.md` with honest statuses + a root-caused bug section.
- Set up session continuity: live log, updated `context.md`, remove the 31 MB session export, archive the stale `.docx` scope docs outside the project.

### Verified in code (audit results)
- Help (?) tooltip already `opacity-100` → **done** (`partials/help-icon.blade.php`).
- Meet incoming-call notification handled (`notification-body.blade.php`, `incoming_call` + `call_type === 'meet'`) → **done** (notification only, no ring/popup).
- Workspace Settings link gated by `$canManageWorkspaceMembers` (`layouts/app.blade.php` ~534) → **done**.
- Group members "Add selected" + picker in same row, `flex flex-col sm:flex-row` (`groups/members/index.blade.php` ~69) → **done**.
- Member-picker status row is `flex flex-wrap` (wraps) → **done**; but "No members selected" is `text-xs` while the statusTrailing span has no explicit size → **font-size distinction open** (`partials/member-picker.blade.php`).

### Audio double-send bug — root cause confirmed
- Flow: pause → Trim/speed (`openRecordModify`) → Continue (`continueFromRecordModify`) → Stop.
- `finalizeRecording()` builds `segments` = [edited WAV per edit …, tail WAV]; merges with `concatAudioFiles(segments)`.
- `concatAudioFiles` (`ct-media-export-script.blade.php:236`) decodes every segment via `decodeAudioData`; a webm/opus segment rejects it. Edited parts can silently stay **webm** because `processAudio(...).catch(() => base)` (`chat-panel-script.blade.php:3066`, same at `:3034`) returns the raw file on decode failure.
- When concat rejects, fallback at `:3101-3102` runs `addFiles(segments)` → **every segment becomes its own attachment** → 2 audios after 1 edit, N+1 after N edits. Matches the report exactly.
- Fix direction recorded in `todo.md` → Confirmed bugs.

### Second verification round (user re-sent the triage list after compaction)
- **Per-user mute**: confirmed **done** — it's the **Participants menu** (head icon, chat/thread header) with per-person flags `notifications / calls / shrink` (shrink = hide messages, tap-to-reveal) + search + bulk multi-select (`chat-participants-menu.blade.php`, `userMutes` state, `messages.user-mutes.save`). User "can't see where to mute" → discoverability; hover-menu + notification-center entries remain open (backlog #1).
- **Hierarchy role-node role selection**: done — role picker sets `role_id` (`saveRole()`/`typeRoleId`, `node.urls.role` PATCH).
- **Hierarchy no-reload**: done — all edits go through AJAX `run()` → `refresh()` → `applyServerNodes` (`hierarchy-map.blade.php:892`), no full reload, tab never resets.
- **Hierarchy multi-select + marquee**: done (`marquee`, ctrl+click, shift+drag at `:470/:658/:677`).
- **Spawn placement**: `spawnIntent`/`placeWithoutOverlap` done — user to visually re-verify sibling distance + add-parent mirror.
- **Workspace Settings gating**: done (`$canManageWorkspaceMembers`, `layouts/app.blade.php`).
- **Meet notification**: done (notification-body, no ring/popup).
- **Help opacity 100%**: done.
- **Registration request refresh gap**: confirmed — `RegistrationService::submit` does **not** `WorkspaceSync::bump` (badge updates via the notification itself, but the pending-request row needs a refresh). Recorded in refresh-rules audit.
- **Video editor**: UNTESTED — recorded in Confirmed bugs (user asked to "make sure it doesn't" have the same bug, not reporting one).
- **Earlier-session todo list** (from opencode UI): all 8 items covered in todo.md — `mapPayload` + map JSON endpoint (refresh), no-reload refresh, tab persistence, role selector, spawn positioning, multi-select + marquee, **dynamic client-rendered SVG edges** (added explicit note), PHP/JS syntax checks (done earlier, `node --check` + 130 tests green).
- **Chat keyboard/focus**: confirmed NOT implemented (`onDraftKeydown` only bound to the draft input) — added as OPEN with the user's exact spec (type-anywhere-to-compose, Enter-always-sends, edit focuses edit input, Ctrl+Enter = click focused element, files-button focus trap).

### Artifacts
- `todo.md` rewritten (statuses + bug section).
- `LIVE_LOG.md` created (this file).
- `context.md` updated (entry point, test count, docx removal).
- `continuing-from-context-md-in-docs-branch.json` deleted (31 MB export).
- Two scope `.docx` archived to `../docs-archive/` and removed from the project.

### Next steps
- Implement the audio-bug fix (never dump segments as separate files; keep baked parts WAV).
- Proceed with the OPEN backlog: mute split + hover/notification-center mute, share system, merge v2, emoji/reactions, pins, hierarchy privileges, call minimize/PiP, member-picker font-size, responsive + refresh audits.
