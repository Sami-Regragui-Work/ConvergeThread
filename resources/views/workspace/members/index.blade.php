@extends('layouts.app')
@section('title', 'Workspace Members')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6" data-sync="users,members,invitations">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-white">Workspace Members</h1>
                <p class="text-sm text-slate-500 mt-1">
                    @if($canManage)
                        Manage people, roles, and pending invitations.
                    @else
                        Everyone in your workspace and their assigned roles.
                    @endif
                </p>
            </div>
            @include('partials.sort-control', [
                'label' => 'Sort',
                'options' => [
                    'display_name:asc' => 'Name A–Z',
                    'display_name:desc' => 'Name Z–A',
                    'email:asc' => 'Email A–Z',
                    'created_at:desc' => 'Newest',
                    'created_at:asc' => 'Oldest',
                ],
            ])
        </div>

        @if($canManage && $pendingInvitations->isNotEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white">Pending invitations</h2>
                    <a href="{{ route('invitations.manage.index') }}" class="text-xs text-brand-400 hover:text-brand-300">View all</a>
                </div>
                <div class="divide-y divide-white/5">
                    @foreach($pendingInvitations as $invitation)
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white">{{ $invitation->email }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $invitation->group ? $invitation->group->name : 'Workspace' }}
                                    · {{ $invitation->tenantRole?->name ?? 'Default' }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('invitations.manage.revoke', $invitation) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 hover:text-red-300">Revoke</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($canManage && $pendingRegistrations->isNotEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5">
                    <h2 class="text-sm font-semibold text-white">Pending registration requests</h2>
                    <p class="text-xs text-slate-500 mt-0.5">People who registered and are waiting for approval to sign in.</p>
                </div>
                <div class="divide-y divide-white/5">
                    @foreach($pendingRegistrations as $registration)
                        <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white break-words">{{ $registration->email }}</p>
                                <p class="text-xs text-slate-500">
                                    @if($registration->display_name){{ $registration->display_name }} · @endif{{ $registration->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <form method="POST" action="{{ route('workspace.registrations.approve', $registration) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-emerald-400 hover:text-emerald-300">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('workspace.registrations.reject', $registration) }}">
                                    @csrf
                                    <button type="button" @click="$dispatch('confirm-action', { message: 'Reject the registration request for ' + @js($registration->email) + '?', form: $el.closest('form') })"
                                        class="text-xs text-red-400 hover:text-red-300">Reject</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($members as $member)
                    @php $roles = $assignableByMember[$member->id] ?? collect(); @endphp
                    <div class="px-5 py-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_6rem] sm:items-center hover:bg-white/5 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold shrink-0"
                                :style="'background-color: ' + @js($member->avatarColor())">
                                {{ $member->avatarInitial() }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-white truncate">{{ $member->displayLabel() }}</p>
                                <p class="text-xs text-slate-500">{{ $member->email }}</p>
                                <p class="text-xs text-brand-400/80 mt-0.5">Role: {{ $member->tenantRole?->name ?? 'Unassigned' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                        @if($canManage && $member->id !== auth()->id() && $roles->isNotEmpty())
                            <form method="POST" action="{{ route('workspace.members.role', $member) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <select name="tenant_role_id" required class="bg-surface-300 border border-white/10 text-white text-xs rounded-lg px-2 py-1.5 min-w-36 max-w-36 truncate">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected($member->tenant_role_id == $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-xs text-brand-400 hover:text-brand-300 px-2 py-1">Set role</button>
                            </form>
                        @endif
                        </div>
                        <div class="flex sm:justify-center">
                        @if(($removableByMember[$member->id] ?? false) && $member->id !== auth()->id())
                            <form method="POST" action="{{ route('workspace.members.destroy', $member) }}" class="shrink-0">
                                @csrf @method('DELETE')
                                <button type="button" @click="$dispatch('confirm-action', { message: 'Remove ' + @js($member->displayLabel()) + ' from the workspace?', form: $el.closest('form') })"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500/80 hover:text-red-400 hover:bg-red-500/10 px-2.5 py-1.5 rounded-lg transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Remove
                                </button>
                            </form>
                        @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No members found.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
