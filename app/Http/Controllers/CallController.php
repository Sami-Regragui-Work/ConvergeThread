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
            'action' => ['required', Rule::in(['invite', 'join', 'offer', 'answer', 'ice', 'leave', 'reject'])],
            'call_id' => 'required|string|max:64',
            'call_type' => ['required', Rule::in(['voice', 'video'])],
            'to_user_id' => 'nullable|integer|exists:users,id',
            'sdp' => 'nullable|array',
            'candidate' => 'nullable|array',
        ]);

        $payload = [
            'action' => $data['action'],
            'call_id' => $data['call_id'],
            'call_type' => $data['call_type'],
            'from_user_id' => $user->id,
            'from_user_name' => $user->displayLabel(),
            'to_user_id' => $data['to_user_id'] ?? null,
            'sdp' => $data['sdp'] ?? null,
            'candidate' => $data['candidate'] ?? null,
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
        } elseif (in_array($data['action'], ['join', 'offer', 'answer', 'ice'], true)) {
            $this->callSessions->touch($chatType, $chatId);
        } elseif (in_array($data['action'], ['leave', 'reject'], true)) {
            if ($data['action'] === 'leave') {
                $this->callSessions->clear($chatType, $chatId, $data['call_id']);
            }
        }

        CallSignal::dispatch($chatType, $chatId, $payload);

        return response()->json(['ok' => true]);
    }

    public function active(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        return response()->json([
            'active' => $this->callSessions->active($chatType, $chatId),
        ]);
    }
}
