# User Flows

## 1. Owner bootstrap (seeded)

1. System seeds owner tenant (`id = 1`) and single owner user
2. Owner logs in → redirected to `/owner`
3. No runtime flow creates additional owners

## 2. Owner → tenant admin invitation

1. Owner opens dashboard, submits admin email
2. System creates invitation with `tenant_id = null`
3. Invitee opens invitation page → accept page
4. Two-step form: account (password) → workspace name
5. System creates new tenant + admin user (system Admin role), marks invitation accepted
6. Redirect to login

## 3. Normal register

1. Guest opens register, submits workspace slug, email, password, optional display name
2. System resolves tenant by slug, creates user, logs in (session regenerated)
3. Redirect to groups
4. Does **not** create tenants or touch tenant 1

## 4. Tenant member invitation

1. Authorized user sends invite (email, optional role, optional group)
2. Invitee opens invitation → accept, submits password + optional display name
3. System validates integrity (tenant, group, role boundaries)
4. Creates user, assigns role, adds to group if specified
5. Redirect to login

## 5. Login / logout

1. Guest submits credentials → session auth, session regenerated
2. Banned users rejected, session cleared
3. Owner → `/owner`; tenant users → `/groups`
4. Logout: POST only, session invalidated, CSRF regenerated

## 6. Groups

1. Tenant user lists groups in their tenant
2. Authorized user creates group (name only)
3. Creator manages group: edit, members, duos, delete
4. Owners do **not** create tenant groups

## 7. Tenant roles

1. System roles (Admin, Moderator, Member) seeded globally
2. Tenant admins create custom roles with permission sets
3. Roles used on invites or as bases for group role overrides

## 8. Group membership

1. Add existing tenant user to group
2. Remove member (soft leave via `left_at`)
3. Assign group role override
4. Re-add restores membership instead of duplicating

## 9. Group role overrides

1. Create override from tenant role base + optional group permissions
2. Assign to members for per-group permission tuning

## 10. Duos

1. Within a group, authorized user creates duo between two users
2. Visible in group; chat access limited to participants
3. Authorized deletion available

## 11. Merge sessions

1. Start session between exactly two groups
2. Temporary shared chat while active
3. End session explicitly → groups return to independent state

## 12. Messaging

1. Open chat by type: group, duo, or merge session
2. Paginated root messages with replies
3. Create, update, delete per policy
4. File upload optional on create

## 13. File threads

1. File message becomes thread parent
2. Dedicated thread view for replies around file/content
3. Remaining work: UX discoverability, not domain logic

## MVP journeys to finish cleanly

- Owner admin invite → first login → tenant admin dashboard
- Tenant invite → group membership → first login
- Group → duo → messaging navigation
- Group → file upload → thread discussion
- Merge session → chat → end session
