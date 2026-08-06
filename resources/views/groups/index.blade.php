@extends('layouts.app')
@section('title', 'Groups')

@php
    $breadcrumbs = [
        ['label' => 'Groups'],
    ];
@endphp

@section('content')
    <div class="max-w-4xl mx-auto" data-sync="groups,members">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-white">Groups</h1>
            <div class="flex items-center gap-2">
                @can('createMember', App\Models\Invitation::class)
                    <button type="button" onclick="document.getElementById('workspace-invite').classList.toggle('hidden')"
                        class="inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 text-slate-300 text-sm font-semibold px-4 py-2 rounded-xl transition">
                        Invite to Workspace
                    </button>
                @endcan
                @can('create', App\Models\Group::class)
                    <a href="{{ route('groups.create') }}"
                        class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Group
                    </a>
                @endcan
            </div>
        </div>

        @can('createMember', App\Models\Invitation::class)
            <div id="workspace-invite" class="hidden mb-6 bg-surface-200 border border-white/5 rounded-2xl px-6 py-5">
                <h2 class="text-sm font-semibold text-white mb-1">Invite someone to your workspace</h2>
                <p class="text-xs text-slate-500 mb-4">Defaults to Moderator if no role is selected.</p>
                <form method="POST" action="{{ route('invitations.tenant.store') }}" class="flex flex-col sm:flex-row gap-3">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ auth()->user()->tenant_id }}">
                    <input type="email" name="email" placeholder="colleague@example.com" required
                        class="flex-1 min-w-0 bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm placeholder-slate-500">
                    <select name="tenant_role_id"
                        class="bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm min-w-40">
                        <option value="">Moderator (default)</option>
                        @foreach($tenantRoles as $role)
                            <option value="{{ $role->id }}" @selected(old('tenant_role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition shrink-0">
                        Send invite
                    </button>
                </form>
            </div>
        @endcan

        @if($groups->isEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl p-12 text-center">
                <div class="w-12 h-12 rounded-xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a4 4 0 00-5-5M9 20H4v-2a4 4 0 015-5m6-4a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <p class="text-slate-400 text-sm">No groups yet. Create your first one.</p>
            </div>
        @else
            <div class="grid gap-3">
                @foreach($groups as $group)
                    <div
                        class="bg-surface-200 border border-white/5 rounded-2xl px-5 py-4 flex items-center gap-4 hover:border-white/10 transition group">
                        <div
                            class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-400 flex items-center justify-center font-bold text-sm shrink-0">
                            {{ strtoupper(substr($group->name, 0, 2)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium text-sm truncate">{{ $group->name }}</p>
                            <p class="text-slate-500 text-xs">{{ $group->active_members_count ?? 0 }} members</p>
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                            {{-- Edit: managers bypass membership; members need update permission --}}
                            @if($managerGroupIds->contains($group->id) || ($memberGroupIds->contains($group->id) && auth()->user()->can('update', $group)))
                                <a href="{{ route('groups.edit', $group) }}"
                                    class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </a>
                            @endif

                            {{-- View (members only) or Join (non-members) --}}
                            @if($memberGroupIds->contains($group->id))
                                <a href="{{ route('groups.show', $group) }}"
                                    class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                            @else
                                @can('join', $group)
                                    <form method="POST" action="{{ route('groups.join', $group) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-500/10 hover:bg-brand-500/20 text-brand-400 text-xs font-semibold transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Join
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
