@extends('layouts.app')
@section('title', 'Members — ' . $group->name)

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('groups.show', $group) }}"
                class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h1 class="text-xl font-bold text-white">Members</h1>
            <span class="text-xs text-slate-500">{{ $group->name }}</span>
        </div>

        @can('invite', $group)
            <div class="bg-surface-200 border border-white/5 rounded-2xl px-6 py-5">
                <h2 class="text-sm font-semibold text-white mb-4">Invite by email</h2>
                <form method="POST" action="{{ route('invitations.tenant.store') }}" class="flex gap-3">
                    @csrf
                    <input type="hidden" name="tenant_id" value="{{ $group->tenant_id }}">
                    <input type="hidden" name="group_id" value="{{ $group->id }}">
                    <input type="email" name="email" placeholder="colleague@example.com" required
                        class="flex-1 bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 placeholder-slate-500">
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                        Send invite
                    </button>
                </form>
                @error('email')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        @endcan

        @can('create', [App\Models\GroupMember::class, $group])
            <div class="bg-surface-200 border border-white/5 rounded-2xl px-6 py-5">
                <h2 class="text-sm font-semibold text-white mb-4">Add existing user</h2>
                <form method="POST" action="{{ route('groups.members.store', $group) }}" class="flex gap-3">
                    @csrf
                    <select name="user_id" required
                        class="flex-1 bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm">
                        <option value="">— Select user —</option>
                        @foreach($availableUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->display_name ?? $user->email }}</option>
                        @endforeach
                    </select>
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white font-semibold px-5 py-2.5 rounded-xl text-sm transition">
                        Add
                    </button>
                </form>
                @error('user_id')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
        @endcan

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($members as $member)
                    <div class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-4 hover:bg-white/5 transition group">
                        <div class="flex items-center gap-4 flex-1 min-w-0">
                            <div
                                class="w-9 h-9 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-sm font-semibold shrink-0">
                                {{ strtoupper(substr($member->user->display_name ?? $member->user->email, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-white font-medium truncate">
                                    {{ $member->user->display_name ?? $member->user->email }}
                                </p>
                                <p class="text-xs text-slate-500">{{ $member->user->email }}</p>
                            </div>
                        </div>

                        @can('assignRole', [App\Models\GroupMember::class, $group])
                            <form method="POST" action="{{ route('groups.members.assign-role', $group) }}"
                                class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <input type="hidden" name="user_id" value="{{ $member->user_id }}">
                                <select name="group_role_override_id"
                                    class="bg-surface-300 border border-white/10 text-white text-xs rounded-lg px-2 py-1.5">
                                    <option value="">No override</option>
                                    @foreach($roleOverrides as $override)
                                        <option value="{{ $override->id }}"
                                            @selected($member->group_role_override_id == $override->id)>
                                            {{ $override->tenantRole->name ?? 'Override' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit"
                                    class="text-xs text-brand-400 hover:text-brand-300 px-2 py-1">Save</button>
                            </form>
                        @endcan

                        @can('delete', [App\Models\GroupMember::class, $group])
                            <form method="POST" action="{{ route('groups.members.destroy', $group) }}"
                                class="opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition">
                                @csrf @method('DELETE')
                                <input type="hidden" name="user_id" value="{{ $member->user_id }}">
                                <button type="submit" onclick="return confirm('Remove this member?')"
                                    class="p-2 rounded-lg hover:bg-red-500/10 text-slate-500 hover:text-red-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No members in this group yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
