<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\RoleHierarchyService;
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

    public function test_workspace_members_page_hides_role_controls_for_unmanageable_users(): void
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
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'name="tenant_role_id"'),
        );
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
}
