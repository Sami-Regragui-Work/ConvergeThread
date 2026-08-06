<?php

namespace Tests\Feature;

use App\Models\GroupMember;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MentionTest extends TestCase
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

    public function test_at_all_creates_mentions_and_notification_for_other_participants(): void
    {
        Notification::fake();

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $author = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'username' => 'alice',
        ]);

        $mentioned = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
            'username' => 'bob',
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('General', $author);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $mentioned->id,
            'joined_at' => now(),
        ]);

        $response = $this->postJson(route('messages.store', ['chatType' => 'group', 'chatId' => $group->id]), [
            'content' => 'Hello @all',
        ]);

        $response->assertOk();

        $messageId = $response->json('message.id');

        $this->assertDatabaseHas('message_mentions', [
            'message_id' => $messageId,
            'user_id' => $mentioned->id,
            'mention_type' => 'all',
        ]);

        $this->assertDatabaseMissing('message_mentions', [
            'message_id' => $messageId,
            'user_id' => $author->id,
        ]);

        Notification::assertSentTo($mentioned, \App\Notifications\MentionedInChatNotification::class);
    }

    public function test_unread_mentions_endpoint_and_mark_read(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $author = User::factory()->create(['tenant_id' => $tenant->id, 'tenant_role_id' => $adminRoleId]);
        $reader = User::factory()->create(['tenant_id' => $tenant->id, 'tenant_role_id' => $adminRoleId]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Ops', $author);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $reader->id,
            'joined_at' => now(),
        ]);

        $message = Message::create([
            'chatable_type' => 'group',
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => '@all ping',
        ]);

        MessageMention::create([
            'message_id' => $message->id,
            'user_id' => $reader->id,
            'mention_type' => 'all',
        ]);

        $this->actingAs($reader);

        $this->getJson(route('messages.mentions', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('message_ids.0', $message->id);

        $this->postJson(route('messages.mentions.read', $message))
            ->assertOk();

        $this->getJson(route('messages.mentions', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonCount(0, 'message_ids');
    }

    public function test_at_username_mention_resolves_participant(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $author = User::factory()->create(['tenant_id' => $tenant->id, 'tenant_role_id' => $adminRoleId]);
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'username' => 'carol',
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Dev', $author);

        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $target->id,
            'joined_at' => now(),
        ]);

        $response = $this->postJson(route('messages.store', ['chatType' => 'group', 'chatId' => $group->id]), [
            'content' => 'Hey @carol check this',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('message_mentions', [
            'message_id' => $response->json('message.id'),
            'user_id' => $target->id,
            'mention_type' => 'user',
        ]);
    }
}
