# Session Notes — 2026-08-11 (Owner feedback + outstanding work)

Priority-ordered backlog captured from the owner's feedback session. Track completion
in this file as work lands. Each item links to the systems it touches.

---

## ⚠️ Testing accounts — standing permission

For manual/browser testing of this running app, I am **allowed to create my own test
accounts, tenants, and groups** (registration, group creation, etc.) — I do NOT need
to wait for or ask the owner for credentials.

Rules that always apply:
- Never modify an account the owner/user created *as a side effect* of testing.
- If I must change the password of an account the user made (e.g. to log in and
  exercise a flow), I **must revert the password at the end** of the work.
- Prefer throwaway accounts I create myself (e.g. `cttest-*@example.com`) over
  touching user accounts.

---

## 1. Error overflow / wrapping (UI hardening)

Errors must never overflow the element that shows them:
- Flash toasts / link banners (`resources/views/partials/flash.blade.php`)
- Confirm dialogs (`resources/views/partials/confirm-dialog.blade.php`)
- Auth forms (`resources/views/auth/*`), including validation errors
- Anywhere a long unbroken string (e.g. a URL or token) is shown:
  ensure `break-words` / `break-all` / `whitespace-pre-wrap` / `max-w-full` + `min-w-0`
  are applied. Audit all error/validation surfaces.

## 2. Registration without invitation → pending approval

Current behavior: self-registration with an existing tenant slug auto-joins the
tenant as system "Member" immediately (AuthService::register).

Required:
- Self-registration must NOT auto-join. It creates a **pending approval** record.
- The tenant's admins / mods / anyone with member-management permissions
  (`invitations.create_member` / `canManageWorkspaceMembers`) must approve or
  reject the request before the user becomes eligible.
- The requesting user must see a **popup/message** explaining the account is
  pending approval (not a silent failure).
- If the entered tenant slug does NOT exist: do not deny — route the request to
  the **owner** for approval instead.
- Owner dashboard: add a section (top or separated) listing pending approvals
  (tenant joins + unknown-slug requests) with approve/reject actions.

Touches: `AuthService`, `RegisterRequest`, `AuthController`, `OwnerController`,
`WorkspaceMemberController`, `User` model (new status fields), new migration,
owner dashboard view, notification to approvers.

**Implemented (2026-08-11):** new `registration_requests` table + `RegistrationRequest`
model + `RegistrationService` (`submit`/`approve`/`reject`). Self-registration no
longer auto-joins — it creates a pending record (hashed password stored) and
redirects to login with an info flash. Approvers (tenant users with
`invitations.create_member`) are DB-notified via `RegistrationApprovalRequiredNotification`;
unknown slugs route to the owner instead. Workspace Members page gained a
"Pending registration requests" section (approve/reject); owner dashboard gained a
Pending Registration Requests section + stat card; owner may act on ANY request
(tenant choice required for unassigned ones). Approved users are created as system
"Member" with their chosen password (reusing the stored hash — User's `hashed` cast
does not re-hash). Routes: `workspace.registrations.approve|reject`,
`owner.registrations.approve|reject`.

## 3. Sorting everywhere

Add "sort by recent" and other relevant sort controls on every list page where it
makes sense:
- Owner dashboard (users, tenants, groups)
- Workspace members
- Invitations
- Groups / group members
- Tenant roles
- Notifications
- Call logs (see #11)
Server-side `orderBy` + a small reusable sort-UI pattern.

**Implemented (2026-08-11):** new `App\Support\SortsLists` trait
(`resolveSort(Request|array, whitelist, default, dir, param='sort', dirParam='dir')`)
plus a reusable `resources/views/partials/sort-control.blade.php` dropdown
(navigates with `?sort=field&dir=dir` / custom param names, preserves other query
params, clears `page`). Wired into: workspace members (name/email/joined),
group members (recently added / name), groups index (newest/name/members),
invitations (newest/oldest/email/expiring), tenant roles (name/created),
notifications (newest/oldest), owner dashboard with per-section params
(`usort`/`udir`, `tsort`/`tdir`, `gsort`/`gdir`; users incl. "Banned first",
tenants incl. "Most users", groups incl. "Most members").

## 4. Member management powers

- Owner: currently can ban but NOT permanently delete a user. Add a permanent
  remove (delete) action for the owner.
- Workspace admins / mods: cannot kick/remove people from the **members** section
  (Workspace Members), only from a group. Add member kick/remove there for users
  with member-management permission.

## 5. Quit group (self-service leave)

No "leave group" exists. Members can only be removed by someone else. Add a
self-service **Leave group** action (uses `GroupMemberService::remove` / sets
`left_at`). Guard: group creator cannot leave (or must transfer/delegate first?).

## 6. Hierarchy enforcement on group membership changes

Removing someone from a group (and group member role changes) must respect the
role hierarchy:
- Tenant founder (main admin) is always protected and can do everything.
- Other admins can do anything except modify another admin / the founder
  (remove them or change their role).
- Mods can only manage ranks below moderator.
- Custom roles follow the custom hierarchy.
Enforcement should live in `RoleHierarchyService` and be applied by
`GroupMemberController::destroy` / `assignRole` / `assignTenantRole` (and
`WorkspaceMemberController`).

## 7. Hierarchy UI → tree graph / nodes

Redesign `hierarchies/index.blade.php` from flat level lists into a **node graph**:
- Create nodes; add any number of children or parents per node.
- **Group nodes**; give a child or parent to the whole created group.
- Two tabs: **Member hierarchies** and **Role hierarchies**.
- Prevent contradictions:
  - Same member cannot sit in two *vertical* (ancestor/descendant) levels.
  - Same member MAY appear in horizontally / vertically-unrelated levels.
  - Example: A top; B,C below; D,E below B. A can't be elsewhere in this tree.
    A person can be in B and C, or C and D/E, but NOT B and D/E unless B/C grouped.
  - Separate node "alpha": anyone can be there until it is linked into the main
    tree — linking runs contradiction checks first.
  - If feasible, also prevent contradictions *between a role and a member*
    (e.g. role "manager" above role "employee" → member with manager rank can't
    sit under a member with employee rank).
- Use a graph library if possible; optional level legend.

Touches: `RoleHierarchy`/`RoleHierarchyLevel` models (+ new node/parent/group
schema), `RoleHierarchyController`, `RoleHierarchyService`, hierarchy views,
member hierarchy + role hierarchy split.

**Implemented as:** no graph library; a hand-rolled `hierarchy-map` canvas
(SVG edges + absolutely-positioned node cards) with pan/zoom/free-drag,
positions in `localStorage`. The "alpha" idea was dropped — unlinked nodes are
simply **level 0 (top level)**. "Add parent" inserts a new empty level above or
links under an existing node; `label` is derived from `level`. Node types
switch freely member ↔ group.

## 8. Group "add existing member" → searchable dropdown

`groups/{group}/members` uses a plain single `<select name="user_id">`.
Replace with the existing Alpine **member-picker** partial (search + select,
multi-select support) and make `GroupMemberController::store` accept `user_ids[]`.

## 9. Profile page

Greenfield. Users need:
- Set / edit display name (in case they skipped it at registration).
- Request a display-name-independent username change.
- Request an email change.
- Change password.
- No avatars/images: keep first-letter avatar + random color; allow the user to
  change the color (persist a `avatar_color` column).
Sidebar user card + profile link.

## 10. Notification sound + call grouping

- Stacked/grouped chat messages must still play the notification sound
  (`NotificationStackService` / `applyUnread` currently only plays when count
  increases).
- Calls from the same person should group like messages.

**Implemented (2026-08-11):**
- `UnreadNotificationsUpdated` now carries `play_sound` (default false);
  `NotificationStackService` dispatches it with `playSound: true` when a message
  stacks onto an existing unread group (count is flat, so the old count-gate
  never fired).
- Frontend `applyUnread(count, playSound)` plays when `count > prev || playSound`;
  both the Echo `.notifications.unread` listener and the `ct-unread` re-broadcast
  (global-call-ring) forward `play_sound`. `playNotifSound()` got a 400ms debounce
  so the two paths can't double-beep. Polling never forces sound (no flag).
- Call grouping: `CallSessionService::notifyParticipants` now reuses an existing
  unread `IncomingCallNotification` for the same chat + caller (increments
  `stack_count`, updates latest `call_id`/`call_type`/`preview`/`url`) instead of
  creating a new row each call; same `playSound: true` on stack.
- Notifications index renders a stack-count badge for calls.

## 11. Call logs (WhatsApp-style)

Calls currently live only in cache (90s TTL) — no history. Build:
- New `call_logs` (and `call_log_participants` / duration segments) tables.
- Each call log: caller, time, status (accepted/denied/missed), total duration.
- Per-user duration + participant timeline (joined / quit / rejoined / declined-
  then-joined), plus total call duration even for someone who missed it all.
- Calls index: compact list (short form) + a **detail page per call**.
- Link from notifications when relevant.

Touches: `CallController`, `CallSessionService`, new models/migrations,
notifications index, new calls views.

## 12. Expandable message notifications

Opening message details from a notification: stacked message notifications
currently show a fixed summary. Add a small expand/collapse arrow (like phone
notification stacks) that reveals the individual messages in the stack before
jumping in.

Touches: `NotificationStackService` (store item details), notifications index
view, header notification rendering.

**Implemented (2026-08-11):**
- `ChatMessageNotification` seeds an `items` array (message_id, author_name,
  preview, created_at); `NotificationStackService` appends to it on each stack,
  capped at the newest 10.
- `notifications/index.blade.php` restructured: stacked chat notifications render
  a chevron toggle (Alpine `x-data="{ expanded: false }"`); expanding reveals each
  message as its own link that deep-links to the exact message via the existing
  `?message=` scroll-to mechanism (`messages/index` + `messages/thread` already
  support it). Shared body moved to
  `resources/views/partials/notification-body.blade.php`.

## 13. Group role overrides scoping + remove self-service join

Owner feedback (2026-08-11): group role overrides should only ever grant
**group-scoped** permissions (tenant-wide powers like `tenant.*`, invitations,
workspace members, tenant roles, `group.create` are out of scope for a single
group), and a member should only enter a group by being invited or being its
creator — remove the "Join" button entirely.

**Implemented (2026-08-11):**
- `Permissions::GROUP_MANAGE = 'group.manage'` (NOT in `all()` — group context
  only) expanding to `group.update`, `group.delete`, `group.invite`,
  `groupmembers.mod`, `grouproleoverrides.manage`. Policy checks already go
  through `GroupPermissionService::hasPermission` which expands, so no policy
  edits were needed.
- New `Permissions::groupScoped()` (leaf group-scoped perms only) +
  `Permissions::groupScopedOptions()` (label map for the picker).
- `StoreGroupRoleOverrideRequest`: `permissions.*` validated with
  `Rule::in(Permissions::groupScoped())` → out-of-scope grants rejected.
- `RoleService::createGroupRoleOverride`: when no explicit permissions are sent,
  the default (`$tenantRole->permissions`) is intersected with `groupScoped()`
  before storing.
- `GroupRoleOverrideController::index` computes `$basePermissions`
  (roleId → `expand(role->permissions) ∩ groupScoped()`) so the modal can show
  base-role grants as already-selected.
- `role-override-modal.blade.php` rewritten: only group-scoped options offered;
  inherited (base-role) permissions render checked + disabled with a
  "from base role" hint; only user-toggled permissions are submitted.
- Join removed end-to-end: `GroupPolicy::join`, `GroupController::join`,
  `GroupService::joinGroup`, route `POST groups/{group}/join` (`groups.join`),
  and both Join buttons (groups index + show). Non-members now see no action on
  the groups list.
- New `tests/Feature/GroupRoleOverrideTest.php` (4 tests): out-of-scope override
  rejected, default override = base role ∩ group-scoped, `group.manage` grants
  update + delete inside the group, join route gone (404).

---

## Progress tracker

| # | Item | Status |
|---|------|--------|
| 1 | Error overflow hardening | ✅ |
| 2 | Pending-approval registration | ✅ |
| 3 | Sorting everywhere | ✅ |
| 4 | Owner permanent delete + workspace kick | ✅ |
| 5 | Quit group | ✅ |
| 6 | Hierarchy on group member changes | ✅ |
| 7 | Hierarchy node graph UI | ▢ |
| 8 | Group add-member searchable picker | ✅ |
| 9 | Profile page | ✅ |
| 10 | Notification sound + call grouping | ▢ |
| 11 | Call logs | ▢ |
| 12 | Expandable notifications | ▢ |
