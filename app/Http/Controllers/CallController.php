<?php

namespace App\Http\Controllers;

use App\Events\CallSignal;
use App\Http\Controllers\Concerns\ResolvesChatable;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CallController extends Controller
{
    use ResolvesChatable;

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

        CallSignal::dispatch($chatType, $chatId, [
            'action' => $data['action'],
            'call_id' => $data['call_id'],
            'call_type' => $data['call_type'],
            'from_user_id' => $user->id,
            'from_user_name' => $user->displayLabel(),
            'to_user_id' => $data['to_user_id'] ?? null,
            'sdp' => $data['sdp'] ?? null,
            'candidate' => $data['candidate'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
