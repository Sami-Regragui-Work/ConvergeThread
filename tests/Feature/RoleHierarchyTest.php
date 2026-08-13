<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\RoleHierarchyService;
use App\Support\Permissions;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SystemTenantRoleSeeder::class,
            SystemTenantSeeder::class,
        ]);
    }

    public function test_moderator_cannot_assign_admin_role(): void
    {
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $modRoleId = TenantRole::where('is_system', true)->where('name', 'Moderator')->value('id');
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $founder = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'email' => 'founder@acme.com',
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'email' => 'admin2@acme.com',
        ]);

        $moderator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $modRoleId,
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $service = app(RoleHierarchyService::class);

        $this->assertFalse($service->canAssignRole($moderator, $admin, TenantRole::find($adminRoleId)));
        $this->assertFalse($service->canAssignRole($moderator, $founder, TenantRole::find($memberRoleId)));
        $this->assertTrue($service->canAssignRole($moderator, $member, TenantRole::find($memberRoleId)));
        $this->assertFalse($service->canAssignRole($admin, $founder, TenantRole::find($memberRoleId)));
        $this->assertTrue($service->canAssignRole($founder, $admin, TenantRole::find($modRoleId)));
    }

    public function test_workspace_members_page_shows_role_controls_disabled_for_unmanageable_users(): void
    {
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $modRoleId = TenantRole::where('is_system', true)->where('name', 'Moderator')->value('id');
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'email' => 'founder@acme.com',
            'display_name' => 'Founder',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'email' => 'admin2@acme.com',
            'display_name' => 'Other Admin',
        ]);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
            'display_name' => 'Emp1',
        ]);

        $moderator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $modRoleId,
            'display_name' => 'Mod 1',
        ]);

        $response = $this->actingAs($moderator)->get(route('workspace.members.index'));

        $response->assertOk();
        $response->assertSee('Founder');
        $response->assertSee('Other Admin');
        $response->assertSee('Emp1');
        $response->assertSee('Set role');
        $content = $response->getContent();
        $this->assertSame(4, substr_count($content, 'name="tenant_role_id"'));
        $this->assertSame(4, substr_count($content, '>Set role</button>'));
        $this->assertSame(3, substr_count($content, "You cannot change this member's role."));
        $this->assertSame(3, substr_count($content, 'opacity-50 cursor-not-allowed">Set role</button>'));
    }

    public function test_member_can_view_workspace_members_but_not_manage_roles(): void
    {
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
            'display_name' => 'Emp1',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
            'display_name' => 'Emp2',
        ]);

        $this->actingAs($member)->get(route('tenant-roles.index'))->assertForbidden();

        $response = $this->actingAs($member)->get(route('workspace.members.index'));

        $response->assertOk();
        $response->assertSee('Emp1');
        $response->assertSee('Emp2');
        $response->assertDontSee('Set role');
        $response->assertDontSee('Pending invitations');
    }

    public function test_system_roles_show_remove_button_disabled_custom_roles_enabled(): void
    {
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'email' => 'admin@acme.com',
        ]);

        TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'Auditor',
            'permissions' => [Permissions::TENANT_ROLES_VIEW],
        ]);

        $content = $this->actingAs($admin)->get(route('tenant-roles.index'))->assertOk()->getContent();

        $this->assertSame(3, substr_count($content, 'System roles cannot be removed'));
        $this->assertSame(1, substr_count($content, 'Delete this role?'));
    }

    public function test_viewer_without_delete_permission_sees_no_remove_buttons(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $viewerRole = TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'RoleViewer',
            'permissions' => [Permissions::TENANT_ROLES_VIEW],
        ]);

        $viewer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $viewerRole->id,
            'email' => 'viewer@acme.com',
        ]);

        $content = $this->actingAs($viewer)->get(route('tenant-roles.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('System roles cannot be removed', $content);
        $this->assertStringNotContainsString('Delete this role?', $content);
    }
}
