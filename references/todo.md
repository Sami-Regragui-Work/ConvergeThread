# ConvergeThread — TODO

## Done

### MVP platform
- Multi-tenant workspaces, system roles (Admin / Moderator / Member), custom tenant roles
- Groups, members, invitations (workspace + group), role assignment on invite
- Group role overrides, duos, two-group merge sessions
- Threaded messaging, file attachments on main chat, Reverb WebSocket realtime (poll fallback)
- Owner dashboard (tenants, users, ban, close workspace)
- Session auth, policies, closed-tenant enforcement
- Breadcrumbs, flash with copyable links, global back navigation
- Responsive layouts (owner dashboard, chat compose, invite forms)
- Attachment download route, 403 go-back

### Merge sessions — workspace isolation
- List and access merge sessions only within the current workspace
- Chat access limited to active members of the two merged groups

### Notifications & mutes
- In-app notification center + unread badge
- Mentions (`@all`, `@selected`, `@role:`, `@username`, merge `@group:` / `@group.user`)
- Chat / thread mute toggles
- Stacked non-mention chat notifications

### Workspace members & hierarchies
- Workspace members page (view for all; role manage for permitted users)
- Role hierarchy chains with level membership sync
- Tenant role colors (picker / hex) used in chat names and mentions

### Chat UX polish
- Full-height chat layout (`fill-height`)
- Multi-file attachments in one message (compose append across picks)
- Typed file cards (PDF/PPT/docs) with size labels; image + video previews
- Inline message edit for own messages
- Confirm popups (replace browser `confirm`)
- Mention pills: `@display name` with rounded highlight
- Keep focus in composer after Enter/send
- Header shows tenant name (app branding stays in sidebar)

### Thread reply attachments
- File uploads on thread replies (same compose path as main chat)

### Invitation management UI
- Pending invitations list + revoke

### Realtime & owner UX
- Laravel Reverb broadcasts for chat messages and workspace list sync
- Owner dashboard live client-side search (no Enter submit)
- Display-name capitalization helper (first letter only)


### Mention UX & duo sync
- Mention pills: no nested/false highlights; darker purple style
- Mention menu: arrow/tab selection; @ button insert works without typing @
- Duo create/delete bumps owner/workspace live sync
- `composer run serve` starts HTTP + Reverb together

---

## Todo

### Voice and video calls
Voice and video call UI exists in chat; **WebRTC media calls are not working yet**

### Sidebar UX
Further polish for desktop toggle defaults on very small screens if needed.

### E2EE
End-to-end encryption for messages and attachments.
