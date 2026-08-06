<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use App\Services\MergeSessionService;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergeSessionTenantTest extends TestCase
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

    public function test_merge_index_only_lists_sessions_for_current_workspace(): void
    {
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $tenantA = Tenant::create(['slug' => 'tenant_a', 'admin_email' => 'admin-a@example.com']);
        $tenantB = Tenant::create(['slug' => 'tenant_b', 'admin_email' => 'admin-b@example.com']);

        $adminA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $adminB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $groupA1 = app(GroupService::class)->create('Team A1', $adminA);
        $groupA2 = app(GroupService::class)->create('Team A2', $adminA);
        $groupB1 = app(GroupService::class)->create('Team B1', $adminB);
        $groupB2 = app(GroupService::class)->create('Team B2', $adminB);

        $sessionA = app(MergeSessionService::class)->start($groupA1, $groupA2);
        app(MergeSessionService::class)->start($groupB1, $groupB2);

        $response = $this->actingAs($adminA)->get(route('merge-sessions.index'));

        $response->assertOk();
        $response->assertSee($sessionA->name);
        $response->assertDontSee('Team B1');
        $response->assertDontSee('Team B2');
    }

    public function test_non_member_cannot_open_merged_chat(): void
    {
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $tenant = Tenant::create(['slug' => 'tenant_a', 'admin_email' => 'admin-a@example.com']);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $outsider = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $group1 = app(GroupService::class)->create('Team One', $admin);
        $group2 = app(GroupService::class)->create('Team Two', $admin);

        $session = app(MergeSessionService::class)->start($group1, $group2);

        $this->actingAs($outsider)
            ->get(route('messages.index', ['merge', $session->id]))
            ->assertForbidden();
    }

    public function test_group_member_can_open_merged_chat(): void
    {
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $tenant = Tenant::create(['slug' => 'tenant_a', 'admin_email' => 'admin-a@example.com']);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $group1 = app(GroupService::class)->create('Team One', $admin);
        $group2 = app(GroupService::class)->create('Team Two', $admin);

        $session = app(MergeSessionService::class)->start($group1, $group2);

        $this->actingAs($admin)
            ->get(route('messages.index', ['merge', $session->id]))
            ->assertOk();
    }
}
