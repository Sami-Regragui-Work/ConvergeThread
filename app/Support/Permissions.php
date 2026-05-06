<?php

namespace App\Support;

final class Permissions
{
    public const TENANT_ALL = 'tenant.*';
    public const TENANT_MOD = 'tenant.mod';

    public const INVITATIONS_CREATE_MEMBER = 'invitations.create_member';

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

    public static function all(): array
    {
        return [
            self::TENANT_ALL,
            self::TENANT_MOD,
            self::INVITATIONS_CREATE_MEMBER,
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
        ];
    }
}