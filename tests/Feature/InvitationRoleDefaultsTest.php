<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\permanents\OwnerSeeder;
use Database\Seeders\permanents\SystemTenantRoleSeeder;
use Database\Seeders\permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationRoleDefaultsTest extends TestCase
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

    public function test_workspace_invite_defaults_to_moderator_role(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $moderatorRoleId = TenantRole::where('is_system', true)->where('name', 'Moderator')->value('id');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($admin)->post(route('invitations.tenant.store'), [
            'tenant_id' => $tenant->id,
            'email' => 'moderator@example.com',
        ]);

        $this->assertDatabaseHas('invitations', [
            'email' => 'moderator@example.com',
            'tenant_id' => $tenant->id,
            'group_id' => null,
            'tenant_role_id' => $moderatorRoleId,
        ]);
    }

    public function test_group_invite_defaults_to_member_role(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $group = app(GroupService::class)->create('Engineering', $admin);

        $this->actingAs($admin)->post(route('invitations.tenant.store'), [
            'tenant_id' => $tenant->id,
            'group_id' => $group->id,
            'email' => 'member@example.com',
        ]);

        $this->assertDatabaseHas('invitations', [
            'email' => 'member@example.com',
            'group_id' => $group->id,
            'tenant_role_id' => $memberRoleId,
        ]);
    }
}
