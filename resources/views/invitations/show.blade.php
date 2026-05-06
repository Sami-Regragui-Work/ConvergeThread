@extends('layouts.app')
@section('title', 'Invitation')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl text-center">
                <div
                    class="w-14 h-14 rounded-2xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center mx-auto mb-5">
                    <svg class="w-7 h-7 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>

                <h1 class="text-xl font-bold text-white mb-2">You've been invited</h1>
                <p class="text-slate-400 text-sm mb-6">
                    @if($invitation->tenant_id === 1)
                        You've been invited to set up the <span class="text-white font-medium">owner account</span>.
                    @else
                        You've been invited to join <span
                            class="text-white font-medium">{{ $invitation->tenant->name ?? 'this workspace' }}</span>.
                        @if($invitation->group)
                            <br>Group: <span class="text-brand-400">{{ $invitation->group->name }}</span>
                        @endif
                        @if($invitation->tenantRole)
                            <br>Role: <span class="text-brand-400">{{ $invitation->tenantRole->name }}</span>
                        @endif
                    @endif
                </p>

                <a href="{{ url('/invitations/' . $invitation->token . '/accept') }}"
                    class="inline-block w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition text-center">
                    Accept Invitation
                </a>

                <p class="text-xs text-slate-600 mt-4">This invitation link is single-use and may expire.</p>
            </div>
        </div>
    </div>
@endsection
