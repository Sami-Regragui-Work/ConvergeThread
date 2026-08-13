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
use App\Services\ChatUserMuteService;
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

    public function test_notification_payload_carries_author_id(): void
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
        $this->assertSame($sender->id, $notification->data['author_id']);
        $this->assertSame($sender->id, $notification->data['items'][0]['author_id']);
    }

    public function test_stacked_notification_keeps_latest_author_id(): void
    {
        [$sender, $other, $recipient] = $this->makeUsers(3);
        $group = app(GroupService::class)->create('Engineering', $sender);
        app(GroupMemberService::class)->add($group, $other, $sender);

        $service = app(NotificationStackService::class);
        $service->notifyChatMessage($this->messageFrom($sender, $group, 'From sender'), 'group', $group->name, $recipient);
        $service->notifyChatMessage($this->messageFrom($other, $group, 'From other'), 'group', $group->name, $recipient);

        $notification = $recipient->fresh()->notifications()->where('type', ChatMessageNotification::class)->firstOrFail();
        $this->assertSame($other->id, $notification->data['author_id']);
        $this->assertSame($other->id, $notification->data['items'][1]['author_id']);
    }

    public function test_notification_index_reports_author_mute_state(): void
    {
        [$sender, $recipient] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $sender);

        app(NotificationStackService::class)->notifyChatMessage(
            $this->messageFrom($sender, $group, 'Hello'),
            'group',
            $group->name,
            $recipient,
        );

        $this->actingAs($recipient)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('notifications.0.muted', false)
            ->assertJsonPath('notifications.0.data.author_id', $sender->id);

        app(ChatUserMuteService::class)->save(
            $recipient,
            $sender->id,
            $group->getMorphClass(),
            (int) $group->id,
            muteNotifications: true,
        );

        $this->actingAs($recipient)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('notifications.0.muted', true);
        $this->actingAs($recipient)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('notificationMute');
    }

    public function test_user_can_mute_specific_person_for_this_chat(): void
    {
        [$viewer, $other] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $viewer);
        app(GroupMemberService::class)->add($group, $other, $viewer);

        $response = $this->actingAs($viewer)
            ->postJson(route('messages.user-mutes.save', ['group', $group->id]), [
                'user_ids' => [$other->id],
                'notifications' => true,
                'calls' => true,
                'shrink' => true,
            ]);

        $response->assertOk()->assertJson([
            'ok' => true,
            'mutes' => [
                $other->id => ['notifications' => true, 'calls' => true, 'shrink' => true],
            ],
        ]);

        $service = app(ChatUserMuteService::class);
        $morph = $group->getMorphClass();

        $this->assertTrue($service->isMuted($viewer, $other->id, $morph, (int) $group->id, 'notifications'));
        $this->assertTrue($service->isMuted($viewer, $other->id, $morph, (int) $group->id, 'calls'));
        $this->assertTrue($service->isMuted($viewer, $other->id, $morph, (int) $group->id, 'shrink'));
        $this->assertSame(
            [$other->id => ['notifications' => true, 'calls' => true, 'shrink' => true]],
            $service->flagsForChat($viewer, $morph, (int) $group->id),
        );
    }

    public function test_chat_mute_toggle_returns_json_and_persists(): void
    {
        [$viewer, $other] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $viewer);
        app(GroupMemberService::class)->add($group, $other, $viewer);

        $service = app(NotificationStackService::class);
        $morph = $group->getMorphClass();

        $this->assertFalse($service->isChatMuted($viewer, $morph, (int) $group->id));

        $response = $this->actingAs($viewer)
            ->postJson(route('messages.mute', ['group', $group->id]));

        $response->assertOk()->assertJson(['muted' => true]);
        $this->assertTrue($service->isChatMuted($viewer, $morph, (int) $group->id));

        $response = $this->actingAs($viewer)
            ->postJson(route('messages.mute', ['group', $group->id]));

        $response->assertOk()->assertJson(['muted' => false]);
        $this->assertFalse($service->isChatMuted($viewer, $morph, (int) $group->id));
    }

    public function test_cannot_mute_user_who_is_not_a_participant(): void
    {
        [$viewer, $outsider] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $viewer);

        $response = $this->actingAs($viewer)
            ->postJson(route('messages.user-mutes.save', ['group', $group->id]), [
                'user_ids' => [$outsider->id],
                'shrink' => true,
            ]);

        $response->assertStatus(422);

        $this->assertSame(
            [],
            app(ChatUserMuteService::class)->flagsForChat($viewer, $group->getMorphClass(), (int) $group->id),
        );
    }

    public function test_messages_from_muted_user_do_not_notify(): void
    {
        [$viewer, $sender] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $sender);
        app(GroupMemberService::class)->add($group, $viewer, $sender);

        app(ChatUserMuteService::class)->save(
            $viewer,
            $sender->id,
            $group->getMorphClass(),
            (int) $group->id,
            muteNotifications: true,
        );

        app(NotificationStackService::class)->notifyChatMessage(
            $this->messageFrom($sender, $group, 'Hello'),
            'group',
            $group->name,
            $viewer,
        );

        $this->assertSame(
            0,
            $viewer->fresh()->notifications()->where('type', ChatMessageNotification::class)->count(),
        );
    }

    public function test_other_members_messages_still_notify_when_one_person_is_muted(): void
    {
        [$viewer, $sender, $other] = $this->makeUsers(3);
        $group = app(GroupService::class)->create('Engineering', $sender);
        app(GroupMemberService::class)->add($group, $viewer, $sender);
        app(GroupMemberService::class)->add($group, $other, $sender);

        app(ChatUserMuteService::class)->save(
            $viewer,
            $sender->id,
            $group->getMorphClass(),
            (int) $group->id,
            muteNotifications: true,
        );

        $service = app(NotificationStackService::class);
        $service->notifyChatMessage($this->messageFrom($sender, $group, 'From muted'), 'group', $group->name, $viewer);
        $service->notifyChatMessage($this->messageFrom($other, $group, 'From other'), 'group', $group->name, $viewer);

        $notification = $viewer->fresh()->notifications()->where('type', ChatMessageNotification::class)->firstOrFail();
        $this->assertSame('From other', $notification->data['preview']);
    }

    public function test_calls_from_muted_caller_do_not_notify(): void
    {
        [$caller, $recipient] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $caller);
        app(GroupMemberService::class)->add($group, $recipient, $caller);

        app(ChatUserMuteService::class)->save(
            $recipient,
            $caller->id,
            $group->getMorphClass(),
            (int) $group->id,
            muteCalls: true,
        );

        app(CallSessionService::class)->notifyParticipants($group, 'group', $caller, 'call_1', 'voice');

        $this->assertSame(
            0,
            $recipient->fresh()->notifications()->where('type', IncomingCallNotification::class)->count(),
        );
    }

    public function test_can_clear_all_flags_by_saving_false(): void
    {
        [$viewer, $other] = $this->makeUsers();
        $group = app(GroupService::class)->create('Engineering', $viewer);
        app(GroupMemberService::class)->add($group, $other, $viewer);
        $service = app(ChatUserMuteService::class);
        $morph = $group->getMorphClass();

        $service->save($viewer, $other->id, $morph, (int) $group->id, true, true, true);
        $this->assertTrue($service->isMuted($viewer, $other->id, $morph, (int) $group->id, 'shrink'));

        $service->save($viewer, $other->id, $morph, (int) $group->id, false, false, false);
        $this->assertFalse($service->isMuted($viewer, $other->id, $morph, (int) $group->id, 'shrink'));
        $this->assertSame([], $service->flagsForChat($viewer, $morph, (int) $group->id));
    }
}
