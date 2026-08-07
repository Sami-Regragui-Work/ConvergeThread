<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MessageAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed([
            SystemTenantRoleSeeder::class,
            SystemTenantSeeder::class,
            OwnerSeeder::class,
        ]);
    }

    public function test_authenticated_member_can_download_message_attachment(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $group = app(GroupService::class)->create('General', $user);

        $path = UploadedFile::fake()->create('notes.txt', 10, 'text/plain')->store('messages', 'public');

        $message = Message::create([
            'chatable_type' => 'group',
            'chatable_id' => $group->id,
            'user_id' => $user->id,
            'content' => null,
            'is_file' => true,
            'file_path' => $path,
        ]);

        $response = $this->actingAs($user)->get(route('messages.attachment', $message));

        $response->assertOk();
    }

    public function test_guest_cannot_download_message_attachment(): void
    {
        $response = $this->get(route('messages.attachment', 1));

        $response->assertRedirect(route('auth.login'));
    }

    public function test_author_can_add_and_remove_attachments_on_edit(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $group = app(GroupService::class)->create('General', $user);

        $oldPath = UploadedFile::fake()->create('old.txt', 5, 'text/plain')->store('messages', 'public');
        $message = Message::create([
            'chatable_type' => 'group',
            'chatable_id' => $group->id,
            'user_id' => $user->id,
            'content' => 'With file',
            'is_file' => true,
        ]);
        $old = $message->attachments()->create([
            'file_path' => $oldPath,
            'original_name' => 'old.txt',
            'mime_type' => 'text/plain',
            'sort' => 0,
        ]);

        $newFile = UploadedFile::fake()->create('new.txt', 8, 'text/plain');

        $this->actingAs($user)
            ->post(route('messages.update', $message), [
                '_method' => 'PATCH',
                'content' => 'Updated text',
                'remove_attachment_ids' => [$old->id],
                'files' => [$newFile],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message.content', 'Updated text');

        $message->refresh()->load('attachments');
        $this->assertCount(1, $message->attachments);
        $this->assertSame('new.txt', $message->attachments->first()->original_name);
        $this->assertDatabaseMissing('message_attachments', ['id' => $old->id]);
    }

    public function test_edit_cannot_leave_message_empty(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_two', 'admin_email' => 'admin2@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $group = app(GroupService::class)->create('General', $user);
        $path = UploadedFile::fake()->create('only.txt', 5, 'text/plain')->store('messages', 'public');
        $message = Message::create([
            'chatable_type' => 'group',
            'chatable_id' => $group->id,
            'user_id' => $user->id,
            'content' => null,
            'is_file' => true,
        ]);
        $attachment = $message->attachments()->create([
            'file_path' => $path,
            'original_name' => 'only.txt',
            'mime_type' => 'text/plain',
            'sort' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('messages.update', $message), [
                '_method' => 'PATCH',
                'empty_content' => true,
                'remove_attachment_ids' => [$attachment->id],
            ])
            ->assertStatus(422);
    }
}
