@extends('layouts.app')
@section('title', 'Invitations')

@php
    $sections = [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
    ];
    $hasClosed = ($invitations->get('accepted') ?? collect())->isNotEmpty()
        || ($invitations->get('cancelled') ?? collect())->isNotEmpty()
        || ($invitations->get('expired') ?? collect())->isNotEmpty();
@endphp

@section('content')
    <div class="max-w-4xl mx-auto space-y-6" data-sync="invitations">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Invitations</h1>
                <p class="text-sm text-slate-500 mt-1">Everyone who has been invited, and where things stand.</p>
            </div>
            <div class="flex items-center gap-2">
                @include('partials.sort-control', [
                    'options' => [
                        'created_at:desc' => 'Newest',
                        'created_at:asc' => 'Oldest',
                        'email:asc' => 'Email A–Z',
                        'email:desc' => 'Email Z–A',
                        'expires_at:asc' => 'Expiring soon',
                    ],
                ])
                @if($hasClosed)
                    <form method="POST" action="{{ route('invitations.manage.clear') }}">
                        @csrf @method('DELETE')
                        <button type="button"
                            @click="$dispatch('confirm-action', { message: 'Permanently remove all accepted, cancelled and expired invitations?', form: $el.closest('form') })"
                            class="text-xs text-red-400 hover:text-red-300 border border-red-500/30 hover:bg-red-500/10 rounded-lg px-3 py-2 transition">
                            Clear closed
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @foreach($sections as $key => $label)
            @php $items = $invitations->get($key, collect()); @endphp
            @if($items->isNotEmpty())
                <section class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-white">{{ $label }}</h2>
                        <span class="text-xs text-slate-500">{{ $items->count() }}</span>
                    </div>
                    <div class="divide-y divide-white/5">
                        @foreach($items as $invitation)
                            <div class="px-5 py-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white truncate">{{ $invitation->email }}</p>
                                    <p class="text-xs text-slate-500">
                                        @if($invitation->tenant_id === null)
                                            Admin invitation
                                        @else
                                            {{ $invitation->tenant?->name }}
                                            · {{ $invitation->group ? $invitation->group->name : 'Workspace' }}
                                        @endif
                                        @if($invitation->tenantRole)
                                            · Role: {{ $invitation->tenantRole->name }}
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        by {{ $invitation->invitedBy?->displayLabel() ?? 'Unknown' }}
                                        @if($key === 'pending')
                                            · expires in {{ $invitation->expires_at?->diffForHumans() }}
                                        @else
                                            · created {{ $invitation->created_at->diffForHumans(null, true) }} ago
                                        @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($key === 'pending')
                                        <form method="POST" action="{{ route('invitations.manage.revoke', $invitation) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-300 px-2 py-1">Revoke</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-500">
                                            @if($key === 'accepted')
                                                Accepted {{ $invitation->accepted_at->diffForHumans(null, true) }} ago
                                            @elseif($key === 'cancelled')
                                                Cancelled {{ $invitation->revoked_at->diffForHumans(null, true) }} ago
                                            @else
                                                Expired {{ $invitation->expires_at->diffForHumans(null, true) }} ago
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if($key === 'pending')
                                <div class="px-5 py-3">
                                    @include('partials.copyable-link', [
                                        'url' => route('invitations.accept', $invitation->token),
                                        'label' => 'Invitation link',
                                    ])
                                </div>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if($invitations->isEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl px-5 py-12 text-center">
                <p class="text-sm text-slate-500">No invitations yet.</p>
            </div>
        @endif
    </div>
@endsection
