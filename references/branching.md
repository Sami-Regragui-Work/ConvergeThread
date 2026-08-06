# Branching Discipline

## Principle

Each coherent change belongs on the branch that matches its domain. `main` is the integration branch.

## Branch map

| Branch prefix | Use for |
|---------------|---------|
| `feature/*` | New capability for that domain (one primary concern) |
| `fix` | Cross-cutting bug fixes |
| `refactor` | Structural changes spanning layers |
| `docs/*` | Documentation only (local files in `references/`, not committed) |

## Workflow

1. Check out the target branch (or create from latest `main`)
2. Merge `main` into the branch before editing
3. Make focused changes, commit with scoped message
4. Merge branch back into `main`
5. Continue on next branch from updated `main`

## Feature branch index

| Branch | Scope |
|--------|-------|
| `feature/auth-controller` | AuthController |
| `feature/auth-service` | AuthService |
| `feature/invitation-controller` | InvitationController |
| `feature/invitation-service` | InvitationService |
| `feature/group-controller` | GroupController |
| `feature/group_member-controller` | GroupMemberController |
| `feature/group_role_override-controller` | GroupRoleOverrideController |
| `feature/duo-controller` | DuoController |
| `feature/merge_session-controller` | MergeSessionController |
| `feature/message-controller` | MessageController |
| `feature/tenant_role-controller` | TenantRoleController |
| `feature/seeders` | Database seeders |
| `feature/views` | Blade templates |
| `feature/routes` | routes/web.php |

## Commit messages

Use conventional prefixes: `feat`, `fix`, `refactor`, `docs`, scoped to the branch domain.

Example: `fix(auth-controller): rename credentials variable after validation`

## Notes

- `feature/JWT-setup` is **deprecated** — do not extend
- Multiple files on one branch is fine when they share one coherent scope
- Global typo fixes across controllers: commit each file on its matching controller branch
