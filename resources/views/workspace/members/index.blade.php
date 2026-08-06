@extends('layouts.app')
@section('title', 'Workspace Members')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6" data-sync="users,members,invitations">
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

        @if($canManage && $pendingInvitations->isNotEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5">
                    <h2 class="text-sm font-semibold text-white">Pending invitations</h2>
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

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($members as $member)
                    @php $roles = $assignableByMember[$member->id] ?? collect(); @endphp
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $member->displayLabel() }}</p>
                            <p class="text-xs text-slate-500">{{ $member->email }}</p>
                            <p class="text-xs text-brand-400/80 mt-0.5">Role: {{ $member->tenantRole?->name ?? 'Unassigned' }}</p>
                        </div>
                        @if($canManage && $member->id !== auth()->id() && $roles->isNotEmpty())
                            <form method="POST" action="{{ route('workspace.members.role', $member) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <select name="tenant_role_id" required class="bg-surface-300 border border-white/10 text-white text-xs rounded-lg px-2 py-1.5 min-w-36">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected($member->tenant_role_id == $role->id)>{{ $role->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-xs text-brand-400 hover:text-brand-300 px-2 py-1">Set role</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No members found.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
