<?php

namespace App\Support;

final class Permissions
{
    public const TENANT_ALL = 'tenant.*';
    public const TENANT_MOD = 'tenant.mod';

    public const INVITATIONS_CREATE_MEMBER = 'invitations.create_member';

    public const WORKSPACE_MEMBERS_VIEW = 'workspace.members.view';

    public const TENANT_ROLES_ALL = 'tenantroles.*';
    public const TENANT_ROLES_VIEW = 'tenantroles.view';
    public const TENANT_ROLES_CREATE = 'tenantroles.create';
    public const TENANT_ROLES_UPDATE = 'tenantroles.update';
    public const TENANT_ROLES_DELETE = 'tenantroles.delete';

    public const GROUP_ALL = 'group.*';
    public const GROUP_MOD = 'group.mod';
    public const GROUP_VIEW = 'group.view';
    public const GROUP_CREATE = 'group.create';
    public const GROUP_UPDATE = 'group.update';
    public const GROUP_DELETE = 'group.delete';
    public const GROUP_INVITE = 'group.invite';
    public const GROUP_MANAGE = 'group.manage';
    public const GROUP_MEET = 'group.meet';

    public const GROUP_MEMBERS_ALL = 'groupmembers.*';
    public const GROUP_MEMBERS_MOD = 'groupmembers.mod';
    public const GROUP_MEMBERS_VIEW = 'groupmembers.view';
    public const GROUP_MEMBERS_ADD = 'groupmembers.add';
    public const GROUP_MEMBERS_REMOVE = 'groupmembers.remove';
    public const GROUP_MEMBERS_ASSIGN_ROLE = 'groupmembers.assignrole';

    public const GROUP_ROLE_OVERRIDES_VIEW = 'grouproleoverrides.view';
    public const GROUP_ROLE_OVERRIDES_MANAGE = 'grouproleoverrides.manage';

    public const MESSAGES_ALL = 'messages.*';
    public const MESSAGES_VIEW = 'messages.view';
    public const MESSAGES_CREATE = 'messages.create';
    public const MESSAGES_UPDATE_OWN = 'messages.update_own';
    public const MESSAGES_DELETE_OWN = 'messages.delete_own';
    public const MESSAGES_DELETE_ANY = 'messages.delete_any';

    public const DUOS_ALL = 'duos.*';
    public const DUOS_VIEW = 'duos.view';
    public const DUOS_CREATE = 'duos.create';
    public const DUOS_DELETE = 'duos.delete';

    public const MERGE_SESSIONS_ALL = 'mergesessions.*';
    public const MERGE_SESSIONS_VIEW = 'mergesessions.view';
    public const MERGE_SESSIONS_CREATE = 'mergesessions.create';
    public const MERGE_SESSIONS_DELETE = 'mergesessions.delete';

    public static function expand(array $granted): array
    {
        $expanded = [];
        $stack = array_values(array_unique($granted));

        while ($stack !== []) {
            $permission = array_pop($stack);

            if (in_array($permission, $expanded, true)) {
                continue;
            }

            $expanded[] = $permission;

            foreach (self::includes($permission) as $included) {
                if (!in_array($included, $expanded, true)) {
                    $stack[] = $included;
                }
            }
        }

        return array_values(array_unique($expanded));
    }

    public static function includes(string $permission): array
    {
        return match ($permission) {
            self::TENANT_ALL => [
                self::TENANT_MOD,
            ],

            self::TENANT_MOD => [
                self::INVITATIONS_CREATE_MEMBER,
                self::WORKSPACE_MEMBERS_VIEW,
                self::TENANT_ROLES_ALL,
                self::GROUP_ALL,
            ],

            self::TENANT_ROLES_ALL => [
                self::TENANT_ROLES_VIEW,
                self::TENANT_ROLES_CREATE,
                self::TENANT_ROLES_UPDATE,
                self::TENANT_ROLES_DELETE,
            ],

            self::GROUP_ALL => [
                self::GROUP_MOD,
                self::GROUP_MEMBERS_ALL,
                self::GROUP_ROLE_OVERRIDES_MANAGE,
                self::DUOS_ALL,
                self::MERGE_SESSIONS_ALL,
                self::MESSAGES_DELETE_ANY,
            ],

            self::GROUP_MOD => [
                self::GROUP_VIEW,
                self::GROUP_CREATE,
                self::GROUP_UPDATE,
                self::GROUP_DELETE,
                self::GROUP_INVITE,
            ],

            self::GROUP_MANAGE => [
                self::GROUP_UPDATE,
                self::GROUP_DELETE,
                self::GROUP_INVITE,
                self::GROUP_MEMBERS_MOD,
                self::GROUP_ROLE_OVERRIDES_MANAGE,
            ],

            self::GROUP_MEMBERS_ALL => [
                self::GROUP_MEMBERS_MOD,
            ],

            self::GROUP_MEMBERS_MOD => [
                self::GROUP_MEMBERS_VIEW,
                self::GROUP_MEMBERS_ADD,
                self::GROUP_MEMBERS_REMOVE,
                self::GROUP_MEMBERS_ASSIGN_ROLE,
            ],

            self::DUOS_ALL => [
                self::DUOS_VIEW,
                self::DUOS_CREATE,
                self::DUOS_DELETE,
            ],

            self::MERGE_SESSIONS_ALL => [
                self::MERGE_SESSIONS_VIEW,
                self::MERGE_SESSIONS_CREATE,
                self::MERGE_SESSIONS_DELETE,
            ],

            self::MESSAGES_ALL => [
                self::MESSAGES_VIEW,
                self::MESSAGES_CREATE,
                self::MESSAGES_UPDATE_OWN,
                self::MESSAGES_DELETE_OWN,
                self::MESSAGES_DELETE_ANY,
            ],

            default => [],
        };
    }

    public static function memberDefaults(): array
    {
        return [
            self::GROUP_VIEW,
            self::GROUP_MEMBERS_VIEW,
            self::GROUP_ROLE_OVERRIDES_VIEW,
            self::MESSAGES_VIEW,
            self::MESSAGES_CREATE,
            self::MESSAGES_UPDATE_OWN,
            self::MESSAGES_DELETE_OWN,
            self::DUOS_VIEW,
            self::MERGE_SESSIONS_VIEW,
            self::GROUP_MEET,
        ];
    }

    public static function requiresGroupMembership(string $permission): bool
    {
        return in_array($permission, [
            self::MESSAGES_VIEW,
            self::MESSAGES_CREATE,
            self::MESSAGES_UPDATE_OWN,
            self::MESSAGES_DELETE_OWN,
            self::MESSAGES_DELETE_ANY,
            self::DUOS_VIEW,
            self::DUOS_CREATE,
            self::DUOS_DELETE,
            self::MERGE_SESSIONS_VIEW,
            self::MERGE_SESSIONS_CREATE,
            self::MERGE_SESSIONS_DELETE,
        ], true);
    }

    /**
     * Permissions that make sense in a group context. Group role overrides only
     * ever apply to the exact group they are created on, so tenant-wide concerns
     * (tenant.*, invitations, tenant roles, workspace members, creating new
     * groups, …) are excluded here.
     *
     * @return list<string>
     */
    public static function groupScoped(): array
    {
        return [
            self::GROUP_VIEW,
            self::GROUP_MANAGE,
            self::GROUP_UPDATE,
            self::GROUP_DELETE,
            self::GROUP_INVITE,
            self::GROUP_MEMBERS_VIEW,
            self::GROUP_MEMBERS_ADD,
            self::GROUP_MEMBERS_REMOVE,
            self::GROUP_MEMBERS_ASSIGN_ROLE,
            self::GROUP_ROLE_OVERRIDES_VIEW,
            self::GROUP_ROLE_OVERRIDES_MANAGE,
            self::MESSAGES_VIEW,
            self::MESSAGES_CREATE,
            self::MESSAGES_UPDATE_OWN,
            self::MESSAGES_DELETE_OWN,
            self::MESSAGES_DELETE_ANY,
            self::DUOS_VIEW,
            self::DUOS_CREATE,
            self::DUOS_DELETE,
            self::MERGE_SESSIONS_VIEW,
            self::MERGE_SESSIONS_CREATE,
            self::MERGE_SESSIONS_DELETE,
            self::GROUP_MEET,
        ];
    }

    /**
     * Human-readable labels for group-scoped permissions (override picker).
     *
     * @return array<string, string>
     */
    public static function groupScopedOptions(): array
    {
        return [
            self::GROUP_VIEW => 'View group',
            self::GROUP_MANAGE => 'Manage this group',
            self::GROUP_UPDATE => 'Rename / edit group',
            self::GROUP_DELETE => 'Delete group',
            self::GROUP_INVITE => 'Invite people to group',
            self::GROUP_MEMBERS_VIEW => 'View group members',
            self::GROUP_MEMBERS_ADD => 'Add group members',
            self::GROUP_MEMBERS_REMOVE => 'Remove group members',
            self::GROUP_MEMBERS_ASSIGN_ROLE => 'Assign group roles',
            self::GROUP_ROLE_OVERRIDES_VIEW => 'View role overrides',
            self::GROUP_ROLE_OVERRIDES_MANAGE => 'Manage role overrides',
            self::MESSAGES_VIEW => 'View messages',
            self::MESSAGES_CREATE => 'Send messages',
            self::MESSAGES_UPDATE_OWN => 'Edit own messages',
            self::MESSAGES_DELETE_OWN => 'Delete own messages',
            self::MESSAGES_DELETE_ANY => 'Delete any message',
            self::DUOS_VIEW => 'View duos',
            self::DUOS_CREATE => 'Create duos',
            self::DUOS_DELETE => 'Delete duos',
            self::MERGE_SESSIONS_VIEW => 'View merge sessions',
            self::MERGE_SESSIONS_CREATE => 'Start merge sessions',
            self::MERGE_SESSIONS_DELETE => 'Delete merge sessions',
            self::GROUP_MEET => 'Start meetings',
        ];
    }

    public static function all(): array
    {
        return [
            self::TENANT_ALL,
            self::TENANT_MOD,
            self::INVITATIONS_CREATE_MEMBER,
            self::WORKSPACE_MEMBERS_VIEW,
            self::TENANT_ROLES_ALL,
            self::TENANT_ROLES_VIEW,
            self::TENANT_ROLES_CREATE,
            self::TENANT_ROLES_UPDATE,
            self::TENANT_ROLES_DELETE,
            self::GROUP_ALL,
            self::GROUP_MOD,
            self::GROUP_VIEW,
            self::GROUP_CREATE,
            self::GROUP_UPDATE,
            self::GROUP_DELETE,
            self::GROUP_INVITE,
            self::GROUP_MEMBERS_ALL,
            self::GROUP_MEMBERS_MOD,
            self::GROUP_MEMBERS_VIEW,
            self::GROUP_MEMBERS_ADD,
            self::GROUP_MEMBERS_REMOVE,
            self::GROUP_MEMBERS_ASSIGN_ROLE,
            self::GROUP_ROLE_OVERRIDES_VIEW,
            self::GROUP_ROLE_OVERRIDES_MANAGE,
            self::MESSAGES_ALL,
            self::MESSAGES_VIEW,
            self::MESSAGES_CREATE,
            self::MESSAGES_UPDATE_OWN,
            self::MESSAGES_DELETE_OWN,
            self::MESSAGES_DELETE_ANY,
            self::DUOS_ALL,
            self::DUOS_VIEW,
            self::DUOS_CREATE,
            self::DUOS_DELETE,
            self::MERGE_SESSIONS_ALL,
            self::MERGE_SESSIONS_VIEW,
            self::MERGE_SESSIONS_CREATE,
            self::MERGE_SESSIONS_DELETE,
            self::GROUP_MEET,
        ];
    }
}