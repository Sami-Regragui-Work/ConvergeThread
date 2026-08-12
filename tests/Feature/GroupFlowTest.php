<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupFlowTest extends TestCase
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

    public function test_group_creator_is_automatically_added_as_member(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);

        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);

        $group = app(GroupService::class)->create('Engineering', $user);

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'left_at' => null,
        ]);
    }

    public function test_creator_can_add_multiple_members_in_one_request(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);

        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $creator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($creator);

        $group = app(GroupService::class)->create('Engineering', $creator);

        $alice = User::factory()->create(['tenant_id' => $tenant->id]);
        $bob = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->post(route('groups.members.store', $group), [
            'user_ids' => [$alice->id, $bob->id],
        ])->assertRedirect(route('groups.members.index', $group))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $alice->id,
            'left_at' => null,
        ]);
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $bob->id,
            'left_at' => null,
        ]);
    }
}
