<?php

namespace Tests\Feature;

use App\Events\CallSignal;
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
        \App\Models\GroupMember::create([
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

        $this->postJson(route('messages.call.signal', ['chatType' => 'group', 'chatId' => $group->id]), [
            'action' => 'leave',
            'call_id' => 'call_1',
            'call_type' => 'voice',
        ])->assertOk();

        $this->getJson(route('messages.call.active', ['chatType' => 'group', 'chatId' => $group->id]))
            ->assertOk()
            ->assertJsonPath('active', null);
    }
}
