<?php

namespace Tests\Feature;

use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageDeleteTest extends TestCase
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

    public function test_author_delete_soft_deletes_for_everyone(): void
    {
        [$author, $group] = $this->memberWithGroup('Admin');

        $message = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => 'Delete me',
        ]);

        $this->actingAs($author)
            ->deleteJson(route('messages.destroy', $message), ['scope' => 'everyone'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message.is_deleted', true)
            ->assertJsonPath('message.deleted_by_name', $author->display_name ?? $author->email);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
        ]);
        $this->assertNotNull($message->fresh()->deleted_at);
        $this->assertNull($message->fresh()->content);
    }

    public function test_author_cannot_hide_own_message(): void
    {
        [$author, $group] = $this->memberWithGroup('Admin');

        $message = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => 'Mine',
        ]);

        // Scope is forced to everyone for own messages; hiding is forbidden by policy.
        $this->actingAs($author)
            ->deleteJson(route('messages.destroy', $message), ['scope' => 'me'])
            ->assertOk()
            ->assertJsonPath('message.is_deleted', true);

        $this->assertDatabaseMissing('message_hides', [
            'message_id' => $message->id,
            'user_id' => $author->id,
        ]);
    }

    public function test_member_can_hide_others_message_but_not_delete_for_everyone(): void
    {
        [$author, $group] = $this->memberWithGroup('Member');
        $other = User::factory()->create([
            'tenant_id' => $author->tenant_id,
            'tenant_role_id' => TenantRole::where('is_system', true)->where('name', 'Member')->value('id'),
        ]);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $other->id,
            'joined_at' => now(),
        ]);

        $message = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => 'Stay',
        ]);

        $this->actingAs($other)
            ->deleteJson(route('messages.destroy', $message), ['scope' => 'everyone'])
            ->assertForbidden();

        $this->actingAs($other)
            ->deleteJson(route('messages.destroy', $message), ['scope' => 'me'])
            ->assertOk()
            ->assertJsonPath('hidden', true);

        $this->assertDatabaseHas('message_hides', [
            'message_id' => $message->id,
            'user_id' => $other->id,
        ]);
        $this->assertNull($message->fresh()->deleted_at);
    }

    public function test_moderator_can_delete_any_message_for_everyone(): void
    {
        [$author, $group] = $this->memberWithGroup('Member');
        $moderator = User::factory()->create([
            'tenant_id' => $author->tenant_id,
            'tenant_role_id' => TenantRole::where('is_system', true)->where('name', 'Moderator')->value('id'),
        ]);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $moderator->id,
            'joined_at' => now(),
        ]);

        $message = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => 'Moderated',
        ]);

        $this->actingAs($moderator)
            ->deleteJson(route('messages.destroy', $message), ['scope' => 'everyone'])
            ->assertOk()
            ->assertJsonPath('message.is_deleted', true)
            ->assertJsonPath('message.deleted_by_id', $moderator->id);

        $this->assertNotNull($message->fresh()->deleted_at);
    }

    public function test_deleting_root_keeps_replies_as_tombstone(): void
    {
        [$author, $group] = $this->memberWithGroup('Admin');

        $root = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => 'Root',
        ]);

        $reply = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'parent_id' => $root->id,
            'content' => 'Reply',
        ]);

        $this->actingAs($author)
            ->deleteJson(route('messages.destroy', $root))
            ->assertOk()
            ->assertJsonPath('message.is_deleted', true);

        $this->assertNotNull($root->fresh()->deleted_at);
        $this->assertDatabaseHas('messages', ['id' => $reply->id, 'content' => 'Reply']);
    }

    /**
     * @return array{0: User, 1: \App\Models\Group}
     */
    private function memberWithGroup(string $roleName): array
    {
        $tenant = Tenant::create(['slug' => 'acme_corp_'.uniqid(), 'admin_email' => 'admin@acme.com']);
        $roleId = TenantRole::where('is_system', true)->where('name', $roleName)->value('id');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $roleId,
        ]);

        $this->actingAs($user);
        $group = app(GroupService::class)->create('General', $user);

        return [$user, $group];
    }
}
