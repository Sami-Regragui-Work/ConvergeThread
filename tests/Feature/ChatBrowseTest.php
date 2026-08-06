<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use App\Support\MessageEncryption;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ChatBrowseTest extends TestCase
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

    public function test_lists_member_chats_and_search_feed_includes_ciphertext(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $author = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('General', $author);

        $cipher = MessageEncryption::PREFIX.'iv:secretbody';
        $message = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => $cipher,
            'is_encrypted' => true,
        ]);

        $this->getJson(route('messages.chats'))
            ->assertOk()
            ->assertJsonFragment(['type' => 'group', 'id' => $group->id, 'name' => 'General']);

        $this->getJson(route('messages.search-feed', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('messages.0.id', $message->id)
            ->assertJsonPath('messages.0.content', $cipher)
            ->assertJsonPath('messages.0.is_encrypted', true);
    }

    public function test_media_groups_files_by_root_thread_including_replies(): void
    {
        Storage::fake('public');

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $author = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Files', $author);

        $root = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => 'Root topic',
        ]);

        $reply = Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $author->id,
            'content' => 'Reply with file',
            'parent_id' => $root->id,
            'is_file' => true,
        ]);

        $path = UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')->store('chat', 'public');
        MessageAttachment::create([
            'message_id' => $reply->id,
            'file_path' => $path,
            'original_name' => 'notes.pdf',
            'mime_type' => 'application/pdf',
            'sort' => 0,
        ]);

        $response = $this->getJson(route('messages.media', ['chatType' => 'group', 'chatId' => $group->id]));

        $response->assertOk();
        $sections = $response->json('sections');
        $this->assertCount(1, $sections);
        $this->assertSame($root->id, $sections[0]['root_id']);
        $this->assertSame($reply->id, $sections[0]['files'][0]['message_id']);
        $this->assertSame('notes.pdf', $sections[0]['files'][0]['name']);
    }

    public function test_locate_points_replies_at_thread_url(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $author = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Locate', $author);

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
            'content' => 'Child',
            'parent_id' => $root->id,
        ]);

        $this->getJson(route('messages.locate', $reply))
            ->assertOk()
            ->assertJsonPath('url', route('messages.thread', $root).'?message='.$reply->id);

        $this->getJson(route('messages.locate', $root))
            ->assertOk()
            ->assertJsonPath('url', route('messages.index', ['group', $group->id]).'?message='.$root->id);
    }

    public function test_non_member_cannot_browse_media(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $author = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);
        $outsider = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Private', $author);

        $this->actingAs($outsider)
            ->getJson(route('messages.media', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertForbidden();
    }
}
