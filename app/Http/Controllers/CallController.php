<?php

namespace App\Http\Controllers;

use App\Events\CallSignal;
use App\Http\Controllers\Concerns\ResolvesChatable;
use App\Models\Message;
use App\Services\CallSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallController extends Controller
{
    use ResolvesChatable;

    public function __construct(
        private readonly CallSessionService $callSessions,
    ) {
    }

    public function signal(Request $request, string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        $data = $request->validate([
            'action' => ['required', Rule::in(['invite', 'join', 'offer', 'answer', 'ice', 'leave', 'reject', 'heartbeat'])],
            'call_id' => 'required|string|max:64',
            'call_type' => ['required', Rule::in(['voice', 'video'])],
            'to_user_id' => 'nullable|integer|exists:users,id',
            'sdp' => 'nullable|array',
            'candidate' => 'nullable|array',
        ]);

        if (in_array($data['action'], ['invite', 'join'], true)) {
            $this->callSessions->assertUserCanJoin(
                (int) $user->id,
                $chatType,
                $chatId,
                $data['call_id'],
            );
        }

        $payload = [
            'action' => $data['action'],
            'call_id' => $data['call_id'],
            'call_type' => $data['call_type'],
            'from_user_id' => $user->id,
            'from_user_name' => $user->displayLabel(),
            'to_user_id' => $data['to_user_id'] ?? null,
            'sdp' => $data['sdp'] ?? null,
            'candidate' => $data['candidate'] ?? null,
            'session_ended' => false,
            'chat_type' => $chatType,
            'chat_id' => $chatId,
        ];

        if ($data['action'] === 'invite') {
            $this->callSessions->rememberInvite($chatType, $chatId, $payload);
            $this->callSessions->notifyParticipants(
                $chatable,
                $chatType,
                $user,
                $data['call_id'],
                $data['call_type'],
            );
        } elseif (in_array($data['action'], ['join', 'offer', 'answer', 'ice', 'heartbeat'], true)) {
            $this->callSessions->markParticipant($chatType, $chatId, (int) $user->id, $data['call_id']);
            $this->callSessions->touch($chatType, $chatId);
        } elseif ($data['action'] === 'leave') {
            $ended = $this->callSessions->removeParticipant(
                $chatType,
                $chatId,
                (int) $user->id,
                $data['call_id'],
            );
            $payload['session_ended'] = $ended;
        }

        if ($data['action'] !== 'heartbeat') {
            CallSignal::dispatch($chatType, $chatId, $payload);
        }

        return response()->json([
            'ok' => true,
            'active' => $this->callSessions->active($chatType, $chatId),
            'session_ended' => $payload['session_ended'],
            'user_call' => $this->callSessions->userActiveCall((int) $user->id),
        ]);
    }

    public function active(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        return response()->json([
            'active' => $this->callSessions->active($chatType, $chatId),
            'user_call' => $this->callSessions->userActiveCall((int) $user->id),
        ]);
    }
}
