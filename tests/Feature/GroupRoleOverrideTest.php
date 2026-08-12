<?php

namespace Tests\Feature;

use App\Models\GroupRoleOverride;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupMemberService;
use App\Services\GroupService;
use App\Services\RoleService;
use App\Support\Permissions;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupRoleOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SystemTenantRoleSeeder::class,
            SystemTenantSeeder::class,
            OwnerSeeder::class,
        ]);
    }

    private function makeTenant(): array
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);

        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        return [$tenant, $admin, $memberRoleId];
    }

    public function test_override_rejects_out_of_scope_permissions(): void
    {
        [$tenant, $admin, $memberRoleId] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $admin);

        $this->actingAs($admin)->post(route('groups.role-overrides.store', $group), [
            'tenant_role_id' => $memberRoleId,
            'permissions' => [Permissions::TENANT_ALL, Permissions::GROUP_UPDATE],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertDatabaseCount('group_role_overrides', 0);
    }

    public function test_override_defaults_to_base_roles_group_scoped_permissions_only(): void
    {
        [$tenant, $admin, $memberRoleId] = $this->makeTenant();

        $role = app(RoleService::class)->createTenantRole($tenant, 'Support', [
            Permissions::TENANT_ALL,
            Permissions::INVITATIONS_CREATE_MEMBER,
            Permissions::WORKSPACE_MEMBERS_VIEW,
            Permissions::GROUP_UPDATE,
            Permissions::GROUP_DELETE,
            Permissions::MESSAGES_VIEW,
        ]);

        $group = app(GroupService::class)->create('Engineering', $admin);

        $this->actingAs($admin)->post(route('groups.role-overrides.store', $group), [
            'tenant_role_id' => $role->id,
        ])->assertSessionHasNoErrors();

        $override = GroupRoleOverride::where('group_id', $group->id)->where('tenant_role_id', $role->id)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [Permissions::GROUP_UPDATE, Permissions::GROUP_DELETE, Permissions::MESSAGES_VIEW],
            $override->permissions,
        );
    }

    public function test_group_manage_override_grants_update_and_delete_in_group(): void
    {
        [$tenant, $admin, $memberRoleId] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $admin);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        app(GroupMemberService::class)->add($group, $member, $admin);

        $memberRole = TenantRole::find($memberRoleId);

        $this->actingAs($member)->patch(route('groups.update', $group), ['name' => 'Changed'])
            ->assertForbidden();

        $override = app(RoleService::class)->createGroupRoleOverride(
            $group,
            $memberRole,
            [Permissions::GROUP_MANAGE],
        );

        app(GroupMemberService::class)->assignRole($group, $member, $override);

        $this->actingAs($member)->patch(route('groups.update', $group), ['name' => 'Changed'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Group updated successfully.');

        $this->actingAs($member)->delete(route('groups.destroy', $group))
            ->assertRedirect(route('groups.index'));
    }

    public function test_join_route_is_removed(): void
    {
        [$tenant, $admin, $memberRoleId] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $admin);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $this->actingAs($member)
            ->post('/groups/'.$group->id.'/join')
            ->assertNotFound();
    }

    public function test_group_accent_color_can_be_updated_and_falls_back_when_cleared(): void
    {
        [$tenant, $admin, $memberRoleId] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $admin);

        $this->actingAs($admin)->patch(route('groups.update', $group), [
            'name' => 'Engineering',
            'accent_color' => '#22c55e',
        ])->assertSessionHas('success', 'Group updated successfully.');

        $this->assertSame('#22c55e', $group->fresh()->accent_color);
        $this->assertSame('#22c55e', $group->fresh()->accentColor());

        $this->actingAs($admin)->patch(route('groups.update', $group), [
            'accent_color' => null,
        ])->assertSessionHas('success', 'Group updated successfully.');

        $this->assertNull($group->fresh()->accent_color);
        $this->assertNotEmpty($group->fresh()->accentColor());
    }

    public function test_invalid_accent_color_is_rejected(): void
    {
        [$tenant, $admin, $memberRoleId] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $admin);

        $this->actingAs($admin)->patch(route('groups.update', $group), [
            'accent_color' => 'red',
        ])->assertSessionHasErrors('accent_color');
    }
}
