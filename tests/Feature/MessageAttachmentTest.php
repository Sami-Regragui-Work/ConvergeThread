<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\permanents\OwnerSeeder;
use Database\Seeders\permanents\SystemTenantRoleSeeder;
use Database\Seeders\permanents\SystemTenantSeeder;
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
}
