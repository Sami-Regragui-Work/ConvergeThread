<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupMemberService;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberRemovalTest extends TestCase
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

    private function role(string $name): int
    {
        return (int) TenantRole::where('is_system', true)->where('name', $name)->value('id');
    }

    public function test_owner_can_remove_user_keeping_messages_and_groups(): void
    {
        $owner = User::factory()->create(['tenant_id' => 1]);

        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);
        $creator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Member'),
            'email' => 'founder@acme.com',
        ]);

        $group = Group::create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'creator_id' => $creator->id,
        ]);

        Message::create([
            'chatable_id' => $group->id,
            'chatable_type' => Group::class,
            'user_id' => $creator->id,
            'content' => 'hello',
        ]);

        $this->actingAs($owner)
            ->delete(route('owner.users.destroy', $creator))
            ->assertRedirect();

        $this->assertNull(User::find($creator->id));
        $this->assertDatabaseHas('messages', [
            'id' => Message::first()->id,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'creator_id' => null,
        ]);
    }

    public function test_owner_can_remove_tenant_with_all_users(): void
    {
        $owner = User::factory()->create(['tenant_id' => 1]);

        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);
        $member = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete(route('owner.tenants.destroy', $tenant))
            ->assertRedirect();

        $this->assertNull(Tenant::find($tenant->id));
        $this->assertNull(User::find($member->id));
    }

    public function test_owner_cannot_remove_owner_workspace(): void
    {
        $owner = User::factory()->create(['tenant_id' => 1]);

        $this->actingAs($owner)
            ->delete(route('owner.tenants.destroy', 1))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('owner.users.destroy', $owner))
            ->assertRedirect()
            ->assertSessionHasErrors('user');
    }

    public function test_workspace_admin_cannot_remove_peer_admin(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Admin'),
            'email' => 'founder@acme.com',
        ]);
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Admin'),
        ]);

        $this->actingAs($admin)
            ->delete(route('workspace.members.destroy', $admin))
            ->assertForbidden();
    }

    public function test_workspace_moderator_can_remove_member(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $moderator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Moderator'),
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Member'),
        ]);

        $group = Group::create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'creator_id' => $moderator->id,
        ]);
        app(GroupMemberService::class)->add($group, $member);

        $this->actingAs($moderator)
            ->delete(route('workspace.members.destroy', $member))
            ->assertRedirect();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $member->id,
        ]);
        $this->assertNotNull(GroupMember::where('group_id', $group->id)->where('user_id', $member->id)->value('left_at'));
        $this->assertNull($member->fresh()->tenant_role_id);
    }

    public function test_group_admin_cannot_remove_peer_admin(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $creator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Member'),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Admin'),
        ]);
        $admin2 = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Admin'),
        ]);

        $group = Group::create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'creator_id' => $creator->id,
        ]);

        app(GroupMemberService::class)->add($group, $admin);
        app(GroupMemberService::class)->add($group, $admin2);

        $this->actingAs($admin)
            ->delete(route('groups.members.destroy', $group), ['user_id' => $admin2->id])
            ->assertForbidden();

        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $admin2->id,
        ]);
    }

    public function test_group_creator_can_remove_any_member(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $creator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Member'),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Admin'),
        ]);

        $group = Group::create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'creator_id' => $creator->id,
        ]);

        app(GroupMemberService::class)->add($group, $creator);
        app(GroupMemberService::class)->add($group, $admin);

        $this->actingAs($creator)
            ->delete(route('groups.members.destroy', $group), ['user_id' => $admin->id])
            ->assertRedirect();

        $this->assertNotNull(GroupMember::where('group_id', $group->id)->where('user_id', $admin->id)->value('left_at'));
    }

    public function test_member_can_leave_group(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $creator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Member'),
        ]);
        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Member'),
        ]);

        $group = Group::create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'creator_id' => $creator->id,
        ]);

        app(GroupMemberService::class)->add($group, $member);

        $this->actingAs($member)
            ->post(route('groups.leave', $group))
            ->assertRedirect(route('groups.index'));

        $this->assertNotNull(GroupMember::where('group_id', $group->id)->where('user_id', $member->id)->value('left_at'));
    }

    public function test_group_creator_cannot_leave_their_group(): void
    {
        $tenant = Tenant::create(['slug' => 'acme', 'admin_email' => 'founder@acme.com']);

        $creator = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $this->role('Member'),
        ]);

        $group = Group::create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'creator_id' => $creator->id,
        ]);

        app(GroupMemberService::class)->add($group, $creator);

        $this->actingAs($creator)
            ->post(route('groups.leave', $group))
            ->assertForbidden();
    }
}
