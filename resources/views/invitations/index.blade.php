@extends('layouts.app')
@section('title', 'Pending Invitations')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6" data-sync="invitations">
        <div>
            <h1 class="text-xl font-bold text-white">Pending Invitations</h1>
            <p class="text-sm text-slate-500 mt-1">Invitations you sent that have not been accepted yet.</p>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($invitations as $invitation)
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white">{{ $invitation->email }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $invitation->group ? 'Group: '.$invitation->group->name : 'Workspace invite' }}
                                · Role: {{ $invitation->tenantRole?->name ?? 'Default' }}
                                · Expires {{ $invitation->expires_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            @include('partials.copyable-link', ['url' => route('invitations.accept', $invitation->token), 'label' => 'Copy link'])
                            <form method="POST" action="{{ route('invitations.manage.revoke', $invitation) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-300 px-2 py-1">Revoke</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No pending invitations.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
