# Project Rules

## Persistent conversation (TOP PRIORITY)

- Persistent conversation across different sessions and different AIs is the number one rule.
- When starting work, first review the project state (recent git history, todo list, `references/AGENTS.md`) to recover full context before acting.
- Keep this file updated so any future session or AI can pick up exactly where the last one left off.

## Workflow

- Always add what the user tells me to do to the todo list first, then start working on it.
- Update the todo list as items are completed, in progress, or blocked.

## UI conventions (decided with the user)

- **List/table views** (members management in groups + workspace, tenant roles, owner tables): action buttons must ALWAYS render for every row — shown disabled (same color/shape as the enabled button, plus `opacity-50`/`cursor-not-allowed`, no hover) when the row is not manageable. Never hide buttons on individual rows; that looks broken in a repeating list.
- **Single-appearance views** (e.g. "Leave group" on groups/show, profile Delete account): do NOT show disabled buttons — hide the control entirely when it doesn't apply.
- Disabled tooltips currently used: group members "You cannot remove yourself." / "The group creator cannot be removed." / "You cannot remove someone at your own level or above."; workspace members "You cannot remove yourself from the workspace." / "The workspace founder cannot be removed." / "You cannot remove someone at your own level or above."
- Dates on closed invitation rows render "… ago" via `diffForHumans(null, true)` to avoid "… from now" when the DB timezone skews timestamps into the future.

## Member-removal authorization

- `GroupMemberPolicy::delete()` blocks self-removal and removing the group creator, then checks `GROUP_MEMBERS_REMOVE` + role hierarchy. `GroupMemberPolicy::deleteAny()` (group creator can always manage; otherwise just the `GROUP_MEMBERS_REMOVE` permission, ignoring hierarchy) gates whether the Remove column renders at all.
- Workspace member removal is gated by `canManage` (`INVITATIONS_CREATE_MEMBER`); `RoleHierarchyService::canManageUser()` decides per-row. The tenant founder (`users.email === tenants.admin_email`) can never be removed by anyone else; `WorkspaceMemberController` passes `protectedMemberIds` for the UI tooltip.

## Date/time

- App timezone is UTC. MySQL `CURRENT_TIMESTAMP`/`useCurrent()` defaults can drift ahead of PHP `now()` if the server timezone isn't UTC — always pass `created_at`/`accepted_at`/`revoked_at` explicitly, and use `diffForHumans(null, true) . ' ago'` for past-event displays.
