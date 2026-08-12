<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GroupMemberService;
use App\Services\GroupService;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDeletionTest extends TestCase
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

    public function test_user_can_delete_own_account(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user);

        $response = $this->delete(route('profile.destroy'), [
            'current_password' => 'password',
        ]);

        $response->assertRedirect(route('auth.login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_owner_cannot_delete_account(): void
    {
        $owner = User::factory()->create(['tenant_id' => 1]);

        $this->actingAs($owner);

        $response = $this->delete(route('profile.destroy'), [
            'current_password' => 'password',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_wrong_password_blocks_deletion(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user);

        $response = $this->delete(route('profile.destroy'), [
            'current_password' => 'not-the-password',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_deletion_removes_group_memberships_but_keeps_group(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $creator = User::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $group = app(GroupService::class)->create('Engineering', $creator);
        app(GroupMemberService::class)->add($group, $user, $creator);

        $this->actingAs($user);

        $this->delete(route('profile.destroy'), [
            'current_password' => 'password',
        ]);

        $this->assertDatabaseMissing('group_members', ['user_id' => $user->id]);
        $this->assertDatabaseHas('groups', ['id' => $group->id]);
    }
}
