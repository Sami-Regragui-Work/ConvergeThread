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
use Tests\TestCase;

class MessagePollTest extends TestCase
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

    public function test_poll_returns_messages_after_given_id(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);

        $group = app(GroupService::class)->create('General', $user);

        $first = Message::create([
            'chatable_type' => 'group',
            'chatable_id' => $group->id,
            'user_id' => $user->id,
            'content' => 'Hello',
        ]);

        $second = Message::create([
            'chatable_type' => 'group',
            'chatable_id' => $group->id,
            'user_id' => $user->id,
            'content' => 'World',
        ]);

        $response = $this->getJson(route('messages.poll', ['chatType' => 'group', 'chatId' => $group->id]) . '?after=' . $first->id);

        $response->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $second->id)
            ->assertJsonPath('messages.0.content', 'World');
    }
}
