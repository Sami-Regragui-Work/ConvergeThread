<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    public function test_workspace_invitation_can_be_accepted_with_system_moderator_role(): void
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

        $invitation = Invitation::where('email', 'moderator@example.com')->firstOrFail();

        Auth::logout();

        $response = $this->post(route('invitations.accept.store', $invitation->token), [
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'display_name' => 'Mod User',
        ]);

        $response->assertRedirect(route('auth.login'));

        $this->assertDatabaseHas('users', [
            'email' => 'moderator@example.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $moderatorRoleId,
        ]);
    }

    public function test_group_invitation_can_be_accepted_with_system_member_role(): void
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

        $invitation = Invitation::where('email', 'member@example.com')->firstOrFail();

        Auth::logout();

        $response = $this->post(route('invitations.accept.store', $invitation->token), [
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'display_name' => 'Member User',
        ]);

        $response->assertRedirect(route('auth.login'));

        $this->assertDatabaseHas('users', [
            'email' => 'member@example.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => User::where('email', 'member@example.com')->value('id'),
        ]);
    }
}
