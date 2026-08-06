<?php

namespace Tests\Feature;

use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use App\Support\MessageEncryption;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class E2eeTest extends TestCase
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

    public function test_stores_encrypted_message_without_server_side_html(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $author = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'e2ee_public_key' => json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => 'a', 'y' => 'b']),
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Secure', $author);

        $cipher = MessageEncryption::PREFIX.'ivtest:ciphertest';

        $response = $this->postJson(route('messages.store', ['chatType' => 'group', 'chatId' => $group->id]), [
            'content' => $cipher,
        ]);

        $response->assertOk()
            ->assertJsonPath('message.is_encrypted', true)
            ->assertJsonPath('message.content', $cipher)
            ->assertJsonPath('message.content_html', null);

        $this->assertDatabaseHas('messages', [
            'id' => $response->json('message.id'),
            'is_encrypted' => true,
            'content' => $cipher,
        ]);
    }

    public function test_crypto_endpoints_accept_public_key_and_shares(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $author = User::factory()->create(['tenant_id' => $tenant->id, 'tenant_role_id' => $adminRoleId]);
        $peer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'e2ee_public_key' => json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => 'x', 'y' => 'y']),
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Keys', $author);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $peer->id,
        ]);

        $publicKey = json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => '1', 'y' => '2']);

        $this->postJson(route('messages.crypto.public-key'), ['public_key' => $publicKey])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $author->id,
            'e2ee_public_key' => $publicKey,
        ]);

        $this->postJson(route('messages.crypto.shares', ['chatType' => 'group', 'chatId' => $group->id]), [
            'shares' => [[
                'user_id' => $author->id,
                'wrapped_key' => 'wrap',
                'ephemeral_public_key' => $publicKey,
            ]],
        ])->assertOk();

        $this->getJson(route('messages.crypto.show', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('has_room_key', true)
            ->assertJsonPath('my_share.wrapped_key', 'wrap');
    }

    public function test_encrypted_mentions_use_client_ids_only(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $author = User::factory()->create(['tenant_id' => $tenant->id, 'tenant_role_id' => $adminRoleId]);
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'username' => 'bob',
        ]);

        $this->actingAs($author);
        $group = app(GroupService::class)->create('Mentions', $author);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $target->id,
        ]);

        $cipher = MessageEncryption::PREFIX.'iv:@bob-should-not-parse';

        $response = $this->postJson(route('messages.store', ['chatType' => 'group', 'chatId' => $group->id]), [
            'content' => $cipher,
            'mention_user_ids' => [$target->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('message_mentions', [
            'message_id' => $response->json('message.id'),
            'user_id' => $target->id,
        ]);
    }
}
