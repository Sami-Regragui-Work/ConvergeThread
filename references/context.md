# ConvergeThread — Working Context

> Fresh-session entry point. Built from the exported Cursor session
> (`cursor_project_refactor_and_updates.md`) plus the commits that landed
> **after** that export (see "Latest commits" below). If something in this
> file disagrees with the code, **code wins** — update this file.

## Project in one line

Work-oriented, multi-tenant team collaboration app: workspaces → groups →
duos + temporary two-group **merge sessions**, with hierarchical roles,
permissioned messaging, E2EE chat, voice/video calls, and rich media.

Ground truth scope docs: `references/Full_Project_functional_scope(1)(1).docx`
and `references/S.O.W_fil_rouge_Sami_Regragui_-MVP(1)(1).docx`.

## Stack & architecture (do not change casually)

- Laravel 12 web **monolith** — session auth (`web` guard), Blade + Tailwind
  (CDN) + Alpine.js. Not API-first, not JWT, not SPA-first.
- PHP `^8.5` target. MySQL. Laravel Reverb for WebSockets. Client E2EE
  (Web Crypto) for message text + attachments.
- Controllers thin → validate → authorize (`Gate`/policies) → service →
  view/redirect. Form Requests for input. Services own domain logic.
- `App\Support\Permissions` constants + `Permissions::expand()` for wildcards.
- Routes: `routes/web.php` only. Channels: `routes/channels.php`.
- Middleware aliases: `ban.check`, `identify.tenant`, `is.owner`, `group.member`.

## Permission model (current, after DB refactor)

Effective access is **two stores, not three**:

- `tenant_roles.permissions` (JSON) — workspace role template (source of truth)
- `group_role_overrides.permissions` (JSON) — per-group customization of a role,
  referenced by `group_members.group_role_override_id`

`group_members.permissions` (the old third copy) was **removed**. Resolution:

```
effective = tenant_role.permissions
          ∪ group_role_override.permissions (via FK)
          ∪ memberDefaults() + creator extras (PHP rules)
          → Permissions::expand()
```

Services: `TenantPermissionService`, `GroupPermissionService`,
`ChatablePermissionService`, `GroupMemberService`. System roles seeded:
Admin / Moderator / Member. `workspace.members.view` is granted to all members
by default; manage controls ride on `invitations.create_member` (Admin/Mod).

## Domain rules / invariants

- **No generic DMs.** Chats are only: group, duo (two users under a group),
  merge session (two groups). Messages polymorphic `chatable_id/type`.
- **Merge sessions** are workspace-scoped; both groups must belong to the
  current tenant; chat access limited to members of both groups.
- **Owner tenant** is `id = 1`; owner dashboard manages tenants/users, ban,
  close/reopen. `tenants.closed_by_id` was removed — closure is recorded in
  `tenant_closures` (`closed_by`, `closed_at`) to break the FK cycle.
- Group creator auto-joins, gets creator extras + "Group creator" label.
- Hierarchy rules: moderators can only manage roles below them; the original
  tenant creator has power over other admins; `roleOverride` grants do **not**
  change hierarchy rank.
- Invitations: owner→admin (`tenant_id` null) vs tenant→member (`tenant_id`
  set). Workspace invite defaults to **Moderator**, group invite to **Member**.
  System roles (tenant_id null) are usable by any tenant. All generated links
  appear in a copyable flash card (no real email).

## Current feature inventory (all shipped)

**Core platform:** tenants, groups, members, duos, merge sessions, invitations
(+ revoke), tenant roles (custom + system, colors), role hierarchies with
levels, workspace members page, pending-invitations page.

**Messaging:** threaded messages, multi-file attachments (≤50 MB/file, ≤20
files/message), edit (text + add/remove files, `edited` marker), delete
(tombstone "Deleted by …", delete-for-me vs delete-for-everyone by permission),
E2EE (account-scoped password-wrapped key backup; room keys cached in
localStorage; server share wins over stale local), markdown compose (live
preview, tables, nested lists, spoilers `>!`/`||`, alerts, footnotes, safe HTML,
highlight.js), fence Monaco editor (opt-in, desktop only, dispose on exit),
mention pills (`@all`, `@selected`, `@role:`, `@user`, merge `@group.user`,
`@group:`), inline media player with speed 0.01–3× + trim (baked on apply/send),
voice notes (record), paste/drop upload, in-app media viewer, WhatsApp-style
file cards with typed thumbnails + colored ext badges.

**Realtime:** Reverb (`composer run serve` = HTTP + Reverb). Chat `message.sent`,
workspace sync (`workspace.updated`), user-channel notifications + badge,
`owner` channel, call signaling. Poll fallback everywhere except calls.

**Calls:** WebRTC mesh (duo/small) + optional LiveKit SFU for group/merge;
voice/video, mute, camera, screen share (separate track — no longer replaces
camera); global in-app ring on any page; one-call-at-a-time lock; optional
TURN; SFU media E2EE via Insertable Streams/SFrame when browser+key support.

**Search & files:** header Search + Files. Proton-style: server serves
ciphertext feed → browser decrypts into IndexedDB → local keyword query
(encrypted at rest). Files grouped by root thread; deep-link `?message=` scroll.
Search requires a selected chat (prevents cross-chat leak).

**UX:** Alpine modals for group create/rename, duo, merge session, tenant role,
hierarchy create, role-override permissions; global back button via session
navigation stack (no ping-pong); themed scrollbars; consistent focus rings;
responsive sidebar drawer; full-height chat; owner live search.

## Tests

51 passing (207 assertions). Key files in `tests/Feature/`: AuthFlow, CallSignal,
ChatBrowse, E2ee, GroupFlow, InvitationFlash, InvitationRoleDefaults,
MarkdownRender, Mention, MergeSessionTenant, MessageAttachment, MessageDelete,
MessagePoll, NavigationStack, RoleHierarchy.

Run: `php artisan test` (or `composer run test`).

## Git workflow

- Active working branch: **`main`** (== `backup` == `origin/main`).
- Docs live in `references/` — **tracked on `docs/project-documentation`**,
  gitignored on `main` (`.gitignore` has `references/`; the branch adds
  `references/.gitignore` keeping only `*.docx` out).
- Historic `feature/*` branches exist but are stale; bulk replay onto them was
  blocked by git automation. Do **not** create per-feature commits across many
  branches anymore unless asked — the team has effectively moved to
  `main`/`backup` + `docs/project-documentation`.
- Push flow used previously: commit on `backup` → merge to `main` → push both,
  then docs on `docs/project-documentation`.

## Latest commits (after the exported conversation)

The export stopped mid-session (last asks: file thumbnails, linter errors,
Monaco freezing). These four commits landed right after:

1. **`196d07b`** — live notifications sync (`UnreadNotificationsUpdated`),
   sessions migration (fixes `SESSION_DRIVER=database` "sessions table doesn't
   exist"), media/UI polish, Monaco hardening.
2. **`af09a50`** — **opt-in Monaco editor**: fresh mount before each create,
   safe sync that never corrupts ```lang fences, graceful fallback error;
   `NotificationController` / `WorkspaceSyncController` linter docblocks
   (`@var User`); `chat-composer-controls` "``` Code editor" button.
3. **`e5fa5bd`** — **screen share no longer replaces camera** in video calls
   (separate track/tile; mesh `RTCRtpSender` + SFU `TrackSubscribed` path).
4. **`b3ca214`** — **colored ext badges**: `MessageAttachment::kind()` now emits
   `code` (html/css/js/ts/py/php/…), `text` (txt/md/log/rst), `archive`
   (zip/rar/…); `Message::toChatPayload` + `message-attachments.blade.php`
   updated to match. Colors: cyan=code, fuchsia=text, amber=archive.

## Known limitations / open items

See `references/known-limitations.md` + `references/todo.md` (docs branch).
Highlights:

- Calls require Reverb; polling can't carry call signaling.
- Mic/camera need HTTPS or localhost (secure context).
- TURN optional; LiveKit SFU optional ops; SFU call E2EE is best-effort
  (Insertable Streams).
- Multi-device E2EE / recovery hardening (account backup covers passphrase
  restore; device linking future).
- Late joiners wait for a room-key share (re-share path exists).
- Notification previews stay opaque (server never sees plaintext); search uses
  a local decrypted index instead (index itself now encrypted at rest).
- Fence Monaco is desktop-only opt-in; still heavier than light suggestions on
  mobile.
- Excel/PPT remain icon+download in-app (no free WYSIWYG); DOCX/text/md/csv
  preview.

## How to run fresh

```bash
composer install
cp .env.example .env            # then set DB_* 
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
composer run serve              # HTTP + Reverb (calls/realtime need this)
```

Optional: `docker run --rm -p 7880:7880 -e LIVEKIT_KEYS="devkey: secret"
livekit/livekit-server --dev` for SFU, plus coturn for TURN (see README).
Seeded owner login comes from `database/seeders/Permanents/OwnerSeeder.php`.
