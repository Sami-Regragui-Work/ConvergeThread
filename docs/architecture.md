# Architecture

## Identity

ConvergeThread is a **Laravel 12 web monolith** — not API-first, not JWT, not SPA-first.

| Layer | Role |
|-------|------|
| Routes | `routes/web.php` only for app flows |
| Controllers | Validate → authorize → call service → view/redirect |
| Form Requests | Input validation |
| Policies | Authorization (`Gate::authorize`) |
| Services | Domain logic, framework-light, explicit parameters |
| Blade | Server-rendered UI with redirects and flash messages |

## Auth

- Guard: `web` (session)
- Login: regenerate session
- Logout: POST-only, invalidate session, regenerate CSRF token
- Banned users: rejected at login and via `BanCheck` middleware

## Tenant model

- **Owner tenant** (`id = 1`): system tenant, seeded; exactly one owner user
- **Normal tenants**: created when an invited admin accepts an owner invitation and names their workspace
- Tenants use `slug` (unique) as identifier; display name is derived from slug
- Normal register joins an **existing** tenant by slug — never creates tenant 1

## Invitation flows (two separate paths)

1. **Owner → tenant admin** (`tenant_id` null on invitation)
   - Owner sends invite from `/owner`
   - Accept creates a **new tenant** + admin user with system Admin role
   - Redirect to login (no auto-login)

2. **Tenant member** (`tenant_id` set)
   - Authorized tenant user invites by email, optional role, optional group
   - Accept creates user in existing tenant, optional group membership
   - Redirect to login

## Permission system

Constants in `App\Support\Permissions`. Expansion via `Permissions::expand()`.

Effective access resolved by:

- `TenantPermissionService` — tenant-level
- `GroupPermissionService` — group membership + overrides
- `ChatablePermissionService` — group / duo / merge session messaging

Chain: `TenantRole.permissions` → `GroupRoleOverride.permissions` → `GroupMember.permissions`

System roles (seeded, `is_system = true`): Admin, Moderator, Member

## Chat structures (only these)

| Type | Scope |
|------|-------|
| Group | Tenant-scoped, membership required |
| Duo | Two users within a parent group |
| Merge session | Temporary bridge between exactly two groups |

No generic DM system. Messages are polymorphic (`chatable_id`, `chatable_type`).

## Route map

```
/auth/register, /auth/login, POST /auth/logout
/invitations/{token}, /invitations/{token}/accept
/owner
/groups, /groups/{group}/members, /groups/{group}/duos, /groups/{group}/role-overrides
/tenant-roles
/merge-sessions
/messages/{chatType}/{chatId}, /messages/{message}/thread
```

## Middleware

| Alias | Purpose |
|-------|---------|
| `ban.check` | Block banned users |
| `identify.tenant` | Bind current tenant context |
| `is.owner` | Owner-only routes |
| `group.member` | Active group membership |

## Models

Tenant, TenantRole, User, Group, GroupRoleOverride, GroupMember, Duo, MergeSession, MergeSessionGroup, Invitation, Message

## Services

AuthService, TenantUserService, InvitationService, GroupService, GroupMemberService, GroupPermissionService, TenantPermissionService, ChatablePermissionService, RoleService, DuoService, MessageService, MergeSessionService

## Policies

GroupPolicy, GroupMemberPolicy, GroupRoleOverridePolicy, DuoPolicy, MergeSessionPolicy, MessagePolicy, InvitationPolicy, TenantRolePolicy

## Seeders (permanent)

`database/seeders/permanents/`:

- SystemTenantRoleSeeder — Admin / Moderator / Member
- SystemTenantSeeder — owner tenant (id = 1)
- OwnerSeeder — single owner user

## Controller pattern

```php
public function store(StoreGroupRequest $request, Group $group)
{
    $credentials = $request->validated();
    Gate::authorize('create', $group);
    $this->groupService->create(/* explicit args from $credentials */);
    return redirect()->route('groups.index')->with('success', '...');
}
```

## Do not reintroduce

- JWT / `auth:api` as main auth
- API Resources for primary flows
- React/Vue as core frontend
- PHP 8.2 references (target 8.5)
- Intentional typos in variable names
