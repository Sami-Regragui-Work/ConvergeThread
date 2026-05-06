<?php

namespace Database\Seeders\permaments;

use App\Models\TenantRole;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class SystemTenantRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TenantRole::updateOrCreate(
            [
                'tenant_id' => null,
                'is_system' => true,
                'name' => 'Admin',
            ],
            [
                'permissions' => [
                    Permissions::GROUP_VIEW,
                    Permissions::GROUP_UPDATE,
                    Permissions::GROUP_DELETE,

                    Permissions::GROUP_MEMBERS_VIEW,
                    Permissions::GROUP_MEMBERS_ADD,
                    Permissions::GROUP_MEMBERS_REMOVE,
                    Permissions::GROUP_MEMBERS_ASSIGN_ROLE,

                    Permissions::GROUP_ROLE_OVERRIDES_VIEW,
                    Permissions::GROUP_ROLE_OVERRIDES_MANAGE,

                    Permissions::DUOS_VIEW,
                    Permissions::DUOS_CREATE,
                    Permissions::DUOS_DELETE,

                    Permissions::MERGE_SESSIONS_VIEW,
                    Permissions::MERGE_SESSIONS_CREATE,
                    Permissions::MERGE_SESSIONS_DELETE,

                    Permissions::MESSAGES_VIEW,
                    Permissions::MESSAGES_CREATE,
                    Permissions::MESSAGES_UPDATE_OWN,
                    Permissions::MESSAGES_DELETE_OWN,
                    Permissions::MESSAGES_DELETE_ANY,

                    Permissions::INVITATIONS_CREATE_MEMBER,

                    Permissions::TENANT_ROLES_VIEW,
                    Permissions::TENANT_ROLES_CREATE,
                    Permissions::TENANT_ROLES_UPDATE,
                    Permissions::TENANT_ROLES_DELETE,
                ],
            ]
        );

        TenantRole::updateOrCreate(
            [
                'tenant_id' => null,
                'is_system' => true,
                'name' => 'Moderator',
            ],
            [
                'permissions' => [
                    Permissions::GROUP_VIEW,

                    Permissions::GROUP_MEMBERS_VIEW,
                    Permissions::GROUP_MEMBERS_ADD,
                    Permissions::GROUP_MEMBERS_REMOVE,

                    Permissions::GROUP_ROLE_OVERRIDES_VIEW,

                    Permissions::DUOS_VIEW,
                    Permissions::DUOS_CREATE,

                    Permissions::MERGE_SESSIONS_VIEW,

                    Permissions::MESSAGES_VIEW,
                    Permissions::MESSAGES_CREATE,
                    Permissions::MESSAGES_UPDATE_OWN,
                    Permissions::MESSAGES_DELETE_OWN,
                    Permissions::MESSAGES_DELETE_ANY,

                    Permissions::INVITATIONS_CREATE_MEMBER,
                ],
            ]
        );

        TenantRole::updateOrCreate(
            [
                'tenant_id' => null,
                'is_system' => true,
                'name' => 'Member',
            ],
            [
                'permissions' => [
                    Permissions::GROUP_VIEW,

                    Permissions::GROUP_MEMBERS_VIEW,

                    Permissions::DUOS_VIEW,

                    Permissions::MERGE_SESSIONS_VIEW,

                    Permissions::MESSAGES_VIEW,
                    Permissions::MESSAGES_CREATE,
                    Permissions::MESSAGES_UPDATE_OWN,
                    Permissions::MESSAGES_DELETE_OWN,
                ],
            ]
        );
    }
}
