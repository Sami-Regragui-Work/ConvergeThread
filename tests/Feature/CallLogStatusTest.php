<?php

namespace Tests\Feature;

use App\Models\CallLog;
use App\Models\CallLogParticipant;
use App\Models\User;
use App\Services\CallLogService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallLogStatusTest extends TestCase
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

    private function makeLog(array $callerStatus, array $callees = []): array
    {
        $caller = User::factory()->create();
        $log = CallLog::create([
            'chat_type' => 'group',
            'chatable_id' => 1,
            'chat_label' => 'Test',
            'call_id' => 'call_'.uniqid(),
            'caller_user_id' => $caller->id,
            'call_type' => 'voice',
            'status' => $callerStatus[1] ?? 'ongoing',
            'started_at' => now()->subMinutes(2),
        ]);

        CallLogParticipant::create([
            'call_log_id' => $log->id,
            'user_id' => $caller->id,
            'role' => 'caller',
            'status' => $callerStatus[0],
            'joined_at' => now()->subMinutes(2),
            'duration' => 0,
        ]);

        $participants = [];

        foreach ($callees as $status) {
            $callee = User::factory()->create();
            $participant = CallLogParticipant::create([
                'call_log_id' => $log->id,
                'user_id' => $callee->id,
                'role' => 'callee',
                'status' => $status[0],
                'joined_at' => $status[0] === 'pending' ? null : now()->subMinute(),
                'duration' => 0,
            ]);
            $participants[$callee->id] = $participant;
        }

        return [$log, $caller, $participants];
    }

    public function test_status_for_is_ongoing_for_everyone(): void
    {
        [$log, $caller] = $this->makeLog(['joined']);

        $this->assertSame('ongoing', $log->statusFor($caller->id));
    }

    public function test_status_for_shows_left_to_the_caller_of_a_completed_call(): void
    {
        [$log, $caller] = $this->makeLog(['left'], [['left']]);
        $log->update(['status' => 'completed']);

        $this->assertSame('left', $log->statusFor($caller->id));
    }

    public function test_status_for_shows_canceled_to_the_caller_who_hung_up_without_an_answer(): void
    {
        [$log, $caller] = $this->makeLog(['left'], [['missed']]);
        $log->update(['status' => 'canceled']);

        $this->assertSame('canceled', $log->statusFor($caller->id));
    }

    public function test_status_for_shows_completed_to_the_callee_who_left_last(): void
    {
        [$log, $caller, $participants] = $this->makeLog(['left'], [['left']]);
        $log->update(['status' => 'completed']);

        $calleeId = array_key_first($participants);

        $this->assertSame('completed', $log->statusFor($calleeId));
    }

    public function test_status_for_shows_left_to_a_callee_when_no_one_else_left(): void
    {
        [$log, $caller, $participants] = $this->makeLog(['left'], [['left']]);
        $log->update(['status' => 'completed']);

        $calleeId = array_key_first($participants);

        // A callee whose own leave was the only leave sees "Left".
        CallLogParticipant::where('role', 'caller')->where('call_log_id', $log->id)->update(['status' => 'joined']);

        $this->assertSame('left', $log->statusFor($calleeId));
    }

    public function test_status_for_shows_declined_and_missed_to_callees(): void
    {
        [$log, $caller, $participants] = $this->makeLog(['left'], [['declined'], ['missed']]);
        $log->update(['status' => 'declined']);

        $ids = array_keys($participants);

        $this->assertSame('declined', $log->statusFor($ids[0]));
        $this->assertSame('missed', $log->statusFor($ids[1]));
    }

    public function test_finalize_marks_a_call_completed_when_a_callee_joined(): void
    {
        $service = app(CallLogService::class);
        [$log, $caller, $participants] = $this->makeLog(['joined'], [['pending']]);
        $calleeId = array_key_first($participants);

        $service->markJoined($log->call_id, $calleeId);
        $service->markLeft($log->call_id, $calleeId);
        $service->markLeft($log->call_id, $caller->id);

        $this->assertSame('completed', $log->fresh()->status);
    }

    public function test_finalize_marks_a_call_canceled_when_the_caller_hung_up_without_an_answer(): void
    {
        $service = app(CallLogService::class);
        [$log, $caller] = $this->makeLog(['joined'], [['pending']]);

        $service->markLeft($log->call_id, $caller->id);

        $this->assertSame('canceled', $log->fresh()->status);
    }

    public function test_finalize_marks_a_call_declined_when_everyone_declined(): void
    {
        $service = app(CallLogService::class);
        [$log, $caller, $participants] = $this->makeLog(['joined'], [['pending']]);
        $calleeId = array_key_first($participants);

        $service->markDeclined($log->call_id, $calleeId);
        $service->markLeft($log->call_id, $caller->id);

        $this->assertSame('declined', $log->fresh()->status);
    }
}
