@extends('layouts.app')
@section('title', 'Workspace Members')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6" data-sync="users,members,invitations">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold text-white">Workspace Members</h1>
                @include('partials.help-icon', ['hint' => 'People in your workspace, their roles, and pending requests.', 'position' => 'bottom'])
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                @include('partials.sort-control', [
                    'options' => [
                        'display_name:asc' => 'Name A–Z',
                        'display_name:desc' => 'Name Z–A',
                        'email:asc' => 'Email A–Z',
                        'created_at:desc' => 'Newest',
                        'created_at:asc' => 'Oldest',
                    ],
                ])
                @if($canManage)
                    <button type="button" onclick="document.getElementById('workspace-invite').classList.toggle('hidden')"
                        class="inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 text-slate-300 text-sm font-semibold px-4 py-2 rounded-xl transition">
                        Invite to Workspace
                    </button>
                @endif
            </div>
        </div>

        <p class="text-sm text-slate-400 -mt-2">
            @if($canManage)
                Everyone in your workspace and their roles. With member-management permissions you can change roles, remove members, and handle pending invitations and registration requests here.
            @else
                Everyone in your workspace and their assigned roles. Invitations and registration requests are handled by members with management permissions.
            @endif
        </p>

        @if($canManage)
            <div id="workspace-invite" class="hidden bg-surface-200 border border-white/5 rounded-2xl px-6 py-5">
                <h2 class="text-sm font-semibold text-white mb-1">Invite someone to your workspace</h2>
                <p class="text-xs text-slate-500 mb-4">Defaults to Moderator if no role is selected.</p>
                <form method="POST" action="{{ route('invitations.tenant.store') }}" class="flex flex-col sm:flex-row gap-3">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ auth()->user()->tenant_id }}">
                    <input type="email" name="email" placeholder="colleague@example.com" required
                        class="flex-1 min-w-0 bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                    <select name="tenant_role_id"
                        class="bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm min-w-40 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                        <option value="">Moderator (default)</option>
                        @foreach($tenantRoles ?? [] as $role)
                            <option value="{{ $role->id }}" @selected(old('tenant_role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition shrink-0">
                        Send invite
                    </button>
                </form>
            </div>
        @endif

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
                        @if($canManage)
                            <form method="POST" action="{{ route('workspace.members.role', $member) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <select name="tenant_role_id" required
                                    @disabled($roles->isEmpty())
                                    @if($roles->isEmpty()) title="You cannot change this member's role." @endif
                                    class="bg-surface-300 border border-white/10 text-white text-xs rounded-lg px-2 py-1.5 min-w-36 max-w-36 truncate {{ $roles->isEmpty() ? 'opacity-50 cursor-not-allowed' : '' }}">
                                    @forelse($roles as $role)
                                        <option value="{{ $role->id }}" @selected($member->tenant_role_id == $role->id)>{{ $role->name }}</option>
                                    @empty
                                        <option value="" selected>{{ $member->tenantRole?->name ?? 'No role' }}</option>
                                    @endforelse
                                </select>
                                <button type="submit" @disabled($roles->isEmpty())
                                    class="text-xs text-brand-400 px-2 py-1 {{ $roles->isEmpty() ? 'opacity-50 cursor-not-allowed' : 'hover:text-brand-300' }}">Set role</button>
                            </form>
                        @endif
                        </div>
                        <div class="flex sm:justify-center">
                        @if($canManage)
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
                            @else
                                @php
                                    $isSelf = $member->id === auth()->id();
                                    $removeTitle = $isSelf
                                        ? 'You cannot remove yourself from the workspace.'
                                        : ($protectedMemberIds->contains($member->id)
                                            ? 'The workspace founder cannot be removed.'
                                            : 'You cannot remove someone at your own level or above.');
                                @endphp
                                <button type="button" disabled title="{{ $removeTitle }}"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500/80 px-2.5 py-1.5 rounded-lg opacity-50 cursor-not-allowed transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Remove
                                </button>
                            @endif
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
