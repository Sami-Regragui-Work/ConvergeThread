<?php

namespace Tests\Feature;

use App\Events\CallSignal;
use App\Models\Duo;
use App\Models\GroupMember;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\IncomingCallNotification;
use App\Services\GroupService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CallSignalTest extends TestCase
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

    public function test_member_can_broadcast_call_signal(): void
    {
        Event::fake([CallSignal::class]);

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);
        $peer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);
        $group = app(GroupService::class)->create('Calls', $user);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $peer->id,
            'joined_at' => now(),
        ]);

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
            'action' => 'invite',
            'call_id' => 'call_1',
            'call_type' => 'voice',
        ])->assertOk();

        Event::assertDispatched(CallSignal::class, function (CallSignal $event) use ($group, $user) {
            return $event->chatType === 'group'
                && $event->chatId === $group->id
                && $event->payload['action'] === 'invite'
                && $event->payload['from_user_id'] === $user->id
                && $event->payload['call_type'] === 'voice';
        });

        $this->getJson(route('messages.call.active', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('active.call_id', 'call_1')
            ->assertJsonPath('active.call_type', 'voice');

        $this->assertTrue(
            $peer->fresh()->notifications()->where('type', IncomingCallNotification::class)->exists()
        );

        // Peer joins — session stays alive even if the starter leaves later.
        $this->actingAs($peer)
            ->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
                'action' => 'join',
                'call_id' => 'call_1',
                'call_type' => 'voice',
            ])->assertOk();

        $this->actingAs($user)
            ->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
                'action' => 'leave',
                'call_id' => 'call_1',
                'call_type' => 'voice',
            ])
            ->assertOk()
            ->assertJsonPath('session_ended', false);

        $this->getJson(route('messages.call.active', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('active.call_id', 'call_1')
            ->assertJsonPath('active.participant_count', 1);

        $this->actingAs($peer)
            ->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
                'action' => 'leave',
                'call_id' => 'call_1',
                'call_type' => 'voice',
            ])
            ->assertOk()
            ->assertJsonPath('session_ended', true);

        $this->getJson(route('messages.call.active', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('active', null);
    }

    public function test_user_cannot_join_two_calls_at_once(): void
    {
        Event::fake([CallSignal::class]);

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);
        $groupA = app(GroupService::class)->create('A', $user);
        $groupB = app(GroupService::class)->create('B', $user);

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $groupA->id]), [
            'action' => 'invite',
            'call_id' => 'call_a',
            'call_type' => 'voice',
        ])->assertOk();

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $groupB->id]), [
            'action' => 'invite',
            'call_id' => 'call_b',
            'call_type' => 'voice',
        ])->assertStatus(422);
    }

    public function test_sfu_token_requires_livekit_config(): void
    {
        config([
            'webrtc.sfu.url' => '',
            'webrtc.sfu.api_key' => '',
            'webrtc.sfu.api_secret' => '',
            'webrtc.sfu.enabled' => false,
        ]);

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);
        $group = app(GroupService::class)->create('Calls', $user);

        $this->postJson(route('messages.call.sfu-token', ['chatType' => 'group', 'chatId' => $group->id]), [
            'call_id' => 'call_sfu',
            'call_type' => 'voice',
        ])->assertNotFound();
    }

    public function test_group_invite_uses_sfu_media_mode_when_livekit_configured(): void
    {
        Event::fake([CallSignal::class]);

        config([
            'webrtc.sfu.url' => 'ws://127.0.0.1:7880',
            'webrtc.sfu.api_key' => 'devkey',
            'webrtc.sfu.api_secret' => 'secret',
            'webrtc.sfu.enabled' => true,
            'webrtc.sfu.force_all' => false,
        ]);

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);
        $group = app(GroupService::class)->create('Calls', $user);

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
            'action' => 'invite',
            'call_id' => 'call_sfu',
            'call_type' => 'voice',
        ])
            ->assertOk()
            ->assertJsonPath('media_mode', 'sfu');

        $this->postJson(route('messages.call.sfu-token', ['chatType' => 'group', 'chatId' => $group->id]), [
            'call_id' => 'call_sfu',
            'call_type' => 'voice',
        ])
            ->assertOk()
            ->assertJsonStructure(['ok', 'url', 'token', 'room']);
    }

    public function test_meet_invite_creates_session_without_ringing_or_notifications(): void
    {
        Event::fake([CallSignal::class]);

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);
        $peer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);
        $group = app(GroupService::class)->create('Calls', $user);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $peer->id,
            'joined_at' => now(),
        ]);

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
            'action' => 'invite',
            'call_id' => 'meet_1',
            'call_type' => 'meet',
        ])
            ->assertOk()
            ->assertJsonPath('active.call_type', 'meet')
            ->assertJsonPath('active.host_user_id', $user->id);

        Event::assertDispatched(CallSignal::class, fn (CallSignal $event) =>
            $event->payload['action'] === 'invite' && $event->payload['call_type'] === 'meet'
        );

        $this->assertFalse(
            $peer->fresh()->notifications()->where('type', IncomingCallNotification::class)->exists()
        );
    }

    public function test_meet_cannot_be_started_in_a_duo(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);
        $peer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);
        $group = app(GroupService::class)->create('Calls', $user);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $peer->id,
            'joined_at' => now(),
        ]);
        $duo = Duo::create([
            'group_id' => $group->id,
            'name' => 'Test Pair',
            'user1_id' => $user->id,
            'user2_id' => $peer->id,
        ]);

        $this->postJson(route('messages.call.signal', ['chatType' => 'duo', 'chatId' => $duo->id]), [
            'action' => 'invite',
            'call_id' => 'meet_duo',
            'call_type' => 'meet',
        ])->assertStatus(422);
    }

    public function test_only_meeting_host_can_end_call_or_mute_remotely(): void
    {
        Event::fake([CallSignal::class]);

        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);
        $peer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($user);
        $group = app(GroupService::class)->create('Calls', $user);
        GroupMember::create([
            'group_id' => $group->id,
            'user_id' => $peer->id,
            'joined_at' => now(),
        ]);

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
            'action' => 'invite',
            'call_id' => 'meet_1',
            'call_type' => 'meet',
        ])->assertOk();

        $this->actingAs($peer)
            ->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
                'action' => 'end_call',
                'call_id' => 'meet_1',
                'call_type' => 'meet',
            ])->assertStatus(422);

        $this->actingAs($peer)
            ->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
                'action' => 'remote_mute',
                'call_id' => 'meet_1',
                'call_type' => 'meet',
                'to_user_id' => $user->id,
            ])->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
                'action' => 'remote_mute',
                'call_id' => 'meet_1',
                'call_type' => 'meet',
                'to_user_id' => $peer->id,
                'muted' => true,
            ])->assertOk();

        Event::assertDispatched(CallSignal::class, function (CallSignal $event) use ($peer) {
            return $event->payload['action'] === 'remote_mute'
                && $event->payload['to_user_id'] === $peer->id
                && $event->payload['muted'] === true;
        });

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
            'action' => 'end_call',
            'call_id' => 'meet_1',
            'call_type' => 'meet',
        ])
            ->assertOk()
            ->assertJsonPath('session_ended', true);

        $this->getJson(route('messages.call.active', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('active', null);
    }
}
