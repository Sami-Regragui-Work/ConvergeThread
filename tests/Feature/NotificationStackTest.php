<?php

namespace Tests\Feature;

use App\Events\UnreadNotificationsUpdated;
use App\Models\Group;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use App\Notifications\IncomingCallNotification;
use App\Services\CallSessionService;
use App\Services\GroupMemberService;
use App\Services\GroupService;
use App\Services\NotificationStackService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationStackTest extends TestCase
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

    private function makeUsers(int $count = 2): array
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $users = [];
        for ($i = 0; $i < $count; $i++) {
            $users[] = User::factory()->create([
                'tenant_id' => $tenant->id,
                'tenant_role_id' => $adminRoleId,
            ]);
        }

        return $users;
    }

    private function messageFrom(User $sender, Group $group, string $content): Message
    {
        return Message::create([
            'chatable_type' => $group->getMorphClass(),
            'chatable_id' => $group->id,
            'user_id' => $sender->id,
            'content' => $content,
        ]);
    }

    public function test_new_chat_notification_seeds_single_item(): void
    {
        [$sender, $recipient] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $sender);

        app(NotificationStackService::class)->notifyChatMessage(
            $this->messageFrom($sender, $group, 'Hello'),
            'group',
            $group->name,
            $recipient,
        );

        $notification = $recipient->fresh()->notifications()->where('type', ChatMessageNotification::class)->firstOrFail();
        $this->assertCount(1, $notification->data['items']);
        $this->assertSame('Hello', $notification->data['items'][0]['preview']);
        $this->assertSame(1, $notification->data['stack_count']);
    }

    public function test_stacked_messages_track_items_and_request_sound(): void
    {
        Event::fake([UnreadNotificationsUpdated::class]);

        [$sender, $recipient] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $sender);

        $service = app(NotificationStackService::class);

        $service->notifyChatMessage($this->messageFrom($sender, $group, 'First'), 'group', $group->name, $recipient);
        $service->notifyChatMessage($this->messageFrom($sender, $group, 'Second'), 'group', $group->name, $recipient);

        $notification = $recipient->fresh()->notifications()->where('type', ChatMessageNotification::class)->firstOrFail();
        $data = $notification->data;

        $this->assertSame(2, $data['stack_count']);
        $this->assertCount(2, $data['items']);
        $this->assertSame('Second', $data['preview']);
        $this->assertSame('Second', $data['items'][1]['preview']);

        Event::assertDispatched(UnreadNotificationsUpdated::class, fn (UnreadNotificationsUpdated $e) => $e->playSound === true);
    }

    public function test_stack_is_capped_at_ten_items(): void
    {
        [$sender, $recipient] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $sender);
        $service = app(NotificationStackService::class);

        for ($i = 1; $i <= 15; $i++) {
            $service->notifyChatMessage($this->messageFrom($sender, $group, "Msg $i"), 'group', $group->name, $recipient);
        }

        $notification = $recipient->fresh()->notifications()->where('type', ChatMessageNotification::class)->firstOrFail();
        $this->assertCount(10, $notification->data['items']);
        $this->assertSame(15, $notification->data['stack_count']);
        $this->assertSame('Msg 15', $notification->data['items'][9]['preview']);
    }

    public function test_calls_from_same_caller_group_into_one_notification(): void
    {
        [$caller, $recipient] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $caller);
        app(GroupMemberService::class)->add($group, $recipient, $caller);

        app(CallSessionService::class)->notifyParticipants($group, 'group', $caller, 'call_1', 'voice');
        app(CallSessionService::class)->notifyParticipants($group, 'group', $caller, 'call_2', 'video');

        $notifications = $recipient->fresh()->notifications()->where('type', IncomingCallNotification::class)->get();
        $this->assertCount(1, $notifications);

        $data = $notifications->first()->data;
        $this->assertSame(2, $data['stack_count']);
        $this->assertSame('call_2', $data['call_id']);
        $this->assertSame('video', $data['call_type']);
    }

    public function test_calls_from_different_callers_stay_separate(): void
    {
        [$caller, $otherCaller, $recipient] = $this->makeUsers(3);
        $group = app(GroupService::class)->create('Engineering', $caller);
        app(GroupMemberService::class)->add($group, $recipient, $caller);

        app(CallSessionService::class)->notifyParticipants($group, 'group', $caller, 'call_1', 'voice');
        app(CallSessionService::class)->notifyParticipants($group, 'group', $otherCaller, 'call_2', 'voice');

        $notifications = $recipient->fresh()->notifications()->where('type', IncomingCallNotification::class)->get();
        $this->assertCount(2, $notifications);
        $this->assertEqualsCanonicalizing(
            ['call_1', 'call_2'],
            $notifications->pluck('data.call_id')->all(),
        );
    }

    public function test_notifications_index_renders_expandable_stack(): void
    {
        [$sender, $recipient] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $sender);

        $service = app(NotificationStackService::class);
        $service->notifyChatMessage($this->messageFrom($sender, $group, 'First'), 'group', $group->name, $recipient);
        $service->notifyChatMessage($this->messageFrom($sender, $group, 'Second'), 'group', $group->name, $recipient);

        $this->actingAs($recipient)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Show messages')
            ->assertSee('?message=')
            ->assertSee('First')
            ->assertSee('Second');
    }
}
