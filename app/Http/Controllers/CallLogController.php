<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Services\CallLogService;
use App\Support\SortsLists;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CallLogController extends Controller
{
    use SortsLists;

    public function __construct(private readonly CallLogService $callLogs)
    {
    }

    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->callLogs->finalizeStale();

        [$sort, $dir] = $this->resolveSort(
            $request,
            ['started_at', 'call_type', 'status', 'total_duration'],
            'started_at',
            'desc',
        );

        $logs = CallLog::query()
            ->where(fn ($query) => $query
                ->where('caller_user_id', $user->id)
                ->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id)))
            ->with(['caller', 'participants.user'])
            ->orderBy($sort, $dir)
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('calls.index', compact('logs'));
    }

    public function show(CallLog $callLog)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        abort_unless(
            (int) $callLog->caller_user_id === (int) $user->id
                || $callLog->participants()->where('user_id', $user->id)->exists(),
            403,
        );

        $callLog->load(['caller', 'participants.user']);

        return view('calls.show', compact('callLog'));
    }
}
