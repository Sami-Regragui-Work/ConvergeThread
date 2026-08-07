<?php

namespace App\Http\Controllers;

use App\Events\CallSignal;
use App\Http\Controllers\Concerns\ResolvesChatable;
use App\Models\Message;
use App\Models\User;
use App\Services\CallSessionService;
use App\Services\LiveKitTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallController extends Controller
{
    use ResolvesChatable;

    public function __construct(
        private readonly CallSessionService $callSessions,
        private readonly LiveKitTokenService $liveKit,
    ) {
    }

    public function signal(Request $request, string $chatType, int $chatId)
    {
        /** @var User $user */
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

        $mediaMode = $this->resolveMediaMode($chatType);

        $payload = [
            'action' => $data['action'],
            'call_id' => $data['call_id'],
            'call_type' => $data['call_type'],
            'media_mode' => $mediaMode,
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
            'media_mode' => $mediaMode,
            'active' => $this->callSessions->active($chatType, $chatId),
            'session_ended' => $payload['session_ended'],
            'user_call' => $this->callSessions->userActiveCall((int) $user->id),
        ]);
    }

    public function active(string $chatType, int $chatId)
    {
        /** @var User $user */
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        return response()->json([
            'active' => $this->callSessions->active($chatType, $chatId),
            'media_mode' => $this->resolveMediaMode($chatType),
            'user_call' => $this->callSessions->userActiveCall((int) $user->id),
        ]);
    }

    public function sfuToken(Request $request, string $chatType, int $chatId)
    {
        /** @var User $user */
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        if (! $this->liveKit->enabled() || $this->resolveMediaMode($chatType) !== 'sfu') {
            return response()->json(['message' => 'SFU is not enabled for this chat.'], 404);
        }

        $data = $request->validate([
            'call_id' => 'required|string|max:64',
            'call_type' => ['required', Rule::in(['voice', 'video'])],
        ]);

        $this->callSessions->assertUserCanJoin(
            (int) $user->id,
            $chatType,
            $chatId,
            $data['call_id'],
        );

        $active = $this->callSessions->active($chatType, $chatId);
        if (! $active || ($active['call_id'] ?? null) !== $data['call_id']) {
            return response()->json(['message' => 'No matching active call.'], 404);
        }

        $room = 'ct_'.$chatType.'_'.$chatId.'_'.$data['call_id'];
        $token = $this->liveKit->mint(
            $room,
            (string) $user->id,
            $user->displayLabel(),
        );

        return response()->json([
            'ok' => true,
            'url' => $this->liveKit->url(),
            'token' => $token,
            'room' => $room,
            'media_mode' => 'sfu',
        ]);
    }

    private function resolveMediaMode(string $chatType): string
    {
        if (! $this->liveKit->enabled()) {
            return 'mesh';
        }

        if (config('webrtc.sfu.force_all')) {
            return 'sfu';
        }

        // Duos stay on mesh (+ TURN); group / merge use SFU when LiveKit is configured.
        return in_array($chatType, ['group', 'merge'], true) ? 'sfu' : 'mesh';
    }
}
