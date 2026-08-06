# ConvergeThread — TODO

## Done

### MVP platform
- Multi-tenant workspaces, system roles (Admin / Moderator / Member), custom tenant roles
- Groups, members, invitations (workspace + group), role assignment on invite
- Group role overrides, duos, two-group merge sessions
- Threaded messaging, file attachments on main chat, poll-based realtime
- Owner dashboard (tenants, users, ban, close workspace)
- Session auth, policies, closed-tenant enforcement
- Breadcrumbs, flash with copyable links, global back navigation
- Responsive layouts (owner dashboard, chat compose, invite forms)
- Attachment download route, 403 go-back

### Merge sessions — workspace isolation
- List and access merge sessions only within the current workspace
- Chat access limited to active members of the two merged groups

---

## Todo

### Video call

### Voice / normal call

### Workspace members (Admin & Moderator)

Sidebar section to list all workspace members outside any group — assign roles (e.g. Moderator) without group membership.

### Sidebar UX

Toggleable sidebar on desktop; closed by default on small screens (mobile overlay exists, desktop toggle pending).

### Thread reply attachments

Allow file uploads on thread replies.

### Multi-file message upload

Send several files in one message.

### File preview in chat

WhatsApp-style previews (or a ConvergeThread-specific treatment) for images, video, documents.

### @selected mentions

Dropup picker when typing `@selected`:

- Header: search (live, per keystroke), **Select all**, **Unselect all**
- Per row: checkbox + member name
- Header also: **Select filtered** / **Unselect filtered** for current search results (clear labels or icons)
- Mention resolves to the chosen subset (with exclusions supported)

### Tagging (SOW #7)

General `@user` mentions and notification hooks — deferred from MVP.

### WebSocket upgrade

Replace polling client with Laravel Reverb or similar.

### Invitation management UI

List pending invitations, revoke / expire from dashboard.
