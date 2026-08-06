<?php

namespace App\Http\Controllers;

use App\Events\ChatKeyNeeded;
use App\Http\Controllers\Concerns\ResolvesChatable;
use App\Models\ChatKeyShare;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatParticipantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ChatCryptoController extends Controller
{
    use ResolvesChatable;

    public function __construct(
        private readonly ChatParticipantService $participantService,
    ) {
    }

    public function storePublicKey(Request $request)
    {
        $data = $request->validate([
            'public_key' => 'required|string|max:4000',
        ]);

        $json = json_decode($data['public_key'], true);
        if (!is_array($json) || ($json['kty'] ?? null) !== 'EC') {
            return response()->json(['message' => 'Invalid public key.'], 422);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->update(['e2ee_public_key' => $data['public_key']]);

        return response()->json(['ok' => true]);
    }

    public function showBackup()
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'backup' => $user->e2ee_private_backup,
            'has_public_key' => (bool) $user->e2ee_public_key,
        ]);
    }

    public function storeBackup(Request $request)
    {
        $data = $request->validate([
            'backup' => 'required|string|max:20000',
        ]);

        $json = json_decode($data['backup'], true);
        if (! is_array($json) || empty($json['salt']) || empty($json['iv']) || empty($json['ciphertext'])) {
            return response()->json(['message' => 'Invalid backup payload.'], 422);
        }

        /** @var User $user */
        $user = Auth::user();
        $user->update(['e2ee_private_backup' => $data['backup']]);

        return response()->json(['ok' => true]);
    }

    public function show(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        $participants = $this->participantService->participants($chatable);

        $share = ChatKeyShare::query()
            ->where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->where('user_id', $user->id)
            ->first();

        $hasRoomKey = ChatKeyShare::query()
            ->where('chatable_type', $chatable->getMorphClass())
            ->where('chatable_id', $chatable->id)
            ->exists();

        return response()->json([
            'participants' => $participants->map(fn (User $p) => [
                'id' => $p->id,
                'public_key' => $p->e2ee_public_key,
            ])->values(),
            'my_share' => $share ? [
                'wrapped_key' => $share->wrapped_key,
                'ephemeral_public_key' => $share->ephemeral_public_key,
            ] : null,
            'has_room_key' => $hasRoomKey,
        ]);
    }

    public function storeShares(Request $request, string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        $data = $request->validate([
            'shares' => 'required|array|min:1',
            'shares.*.user_id' => 'required|integer|exists:users,id',
            'shares.*.wrapped_key' => 'required|string|max:8000',
            'shares.*.ephemeral_public_key' => 'required|string|max:4000',
        ]);

        $participantIds = $this->participantService->participants($chatable)->pluck('id')->all();
        $type = $chatable->getMorphClass();

        foreach ($data['shares'] as $share) {
            if (!in_array((int) $share['user_id'], array_map('intval', $participantIds), true)) {
                continue;
            }

            ChatKeyShare::updateOrCreate(
                [
                    'chatable_type' => $type,
                    'chatable_id' => $chatable->id,
                    'user_id' => $share['user_id'],
                ],
                [
                    'wrapped_key' => $share['wrapped_key'],
                    'ephemeral_public_key' => $share['ephemeral_public_key'],
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    public function requestKey(string $chatType, int $chatId)
    {
        $user = Auth::user();
        $chatable = $this->resolveChatable($user, $chatType, $chatId);
        Gate::authorize('viewAny', [Message::class, $chatable]);

        ChatKeyNeeded::dispatch($chatType, $chatId, (int) $user->id);

        return response()->json(['ok' => true]);
    }
}
