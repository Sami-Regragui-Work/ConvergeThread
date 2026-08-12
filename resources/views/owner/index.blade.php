@extends('layouts.app')

@section('title', 'Owner Dashboard')

@section('content')
    <div class="space-y-8" data-sync="users,tenants,groups,members,duos">
        <section class="space-y-4">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">Owner Dashboard</h1>
                    <p class="text-sm text-slate-400">
                        Bootstrap tenant admins, inspect platform data, and review current counts.
                    </p>
                </div>
                <div class="md:hidden">
                    <input type="search" x-model="ownerSearch" placeholder="Search tenants, users…"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                        @keydown.enter.prevent>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <div class="rounded-2xl border border-white/5 bg-surface-200 px-5 py-4 shadow-xl shadow-black/10">
                <p class="text-xs uppercase tracking-wide text-slate-500">Users</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['users_count'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/5 bg-surface-200 px-5 py-4 shadow-xl shadow-black/10">
                <p class="text-xs uppercase tracking-wide text-slate-500">Banned Users</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['banned_users_count'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/5 bg-surface-200 px-5 py-4 shadow-xl shadow-black/10">
                <p class="text-xs uppercase tracking-wide text-slate-500">Tenants</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['tenants_count'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/5 bg-surface-200 px-5 py-4 shadow-xl shadow-black/10">
                <p class="text-xs uppercase tracking-wide text-slate-500">Closed Tenants</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['closed_tenants_count'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/5 bg-surface-200 px-5 py-4 shadow-xl shadow-black/10">
                <p class="text-xs uppercase tracking-wide text-slate-500">Groups</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['groups_count'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/5 bg-surface-200 px-5 py-4 shadow-xl shadow-black/10">
                <p class="text-xs uppercase tracking-wide text-slate-500">Duos</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $stats['duos_count'] }}</p>
            </div>

            <div class="rounded-2xl border border-white/5 bg-surface-200 px-5 py-4 shadow-xl shadow-black/10">
                <p class="text-xs uppercase tracking-wide text-slate-500">Pending Registrations</p>
                <p class="mt-2 text-2xl font-semibold text-white {{ $stats['pending_registrations_count'] > 0 ? 'text-amber-300' : '' }}">{{ $stats['pending_registrations_count'] }}</p>
            </div>
        </section>

        <section class="space-y-6">
            @if($pendingRegistrations->isNotEmpty())
                <div class="rounded-2xl border border-white/5 bg-surface-200 p-6 shadow-xl shadow-black/10">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-white">Pending Registration Requests</h2>
                        <p class="mt-1 text-sm text-slate-400">
                            People waiting for approval to sign in. Pick a tenant to approve an unassigned request into, or reject it.
                        </p>
                    </div>

                    <div class="divide-y divide-white/5">
                        @foreach($pendingRegistrations as $registration)
                            <div class="py-4 flex flex-col lg:flex-row lg:items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white break-words">{{ $registration->email }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 break-words">
                                        @if($registration->display_name){{ $registration->display_name }} · @endif
                                        @if($registration->tenant)
                                            Joins: <span class="text-slate-400">{{ $registration->tenant->name }}</span> ·
                                        @else
                                            Requested slug: <span class="text-slate-400">{{ $registration->tenant_slug ?? '—' }}</span> ·
                                        @endif
                                        {{ $registration->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('owner.registrations.approve', $registration) }}"
                                    class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0">
                                    @csrf
                                    @if(!$registration->tenant)
                                        <select name="tenant_id" required
                                            class="bg-surface-300 border border-white/10 text-white text-xs rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                                            @foreach($tenants->where('id', '!=', 1) as $tenant)
                                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="submit" class="text-xs text-emerald-400 hover:text-emerald-300 border border-white/10 rounded-lg px-3 py-2 transition">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('owner.registrations.reject', $registration) }}" class="shrink-0">
                                    @csrf
                                    <button type="button" @click="$dispatch('confirm-action', { message: 'Reject the registration request for ' + @js($registration->email) + '?', form: $el.closest('form') })"
                                        class="w-full lg:w-auto text-xs text-red-400 hover:text-red-300 border border-white/10 rounded-lg px-3 py-2 transition">Reject</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-white/5 bg-surface-200 p-6 shadow-xl shadow-black/10">
                    <div class="mb-5 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Invite Tenant Admin</h2>
                            <p class="mt-1 text-sm text-slate-400">This uses the existing owner invitation flow.</p>
                        </div>
                        <a href="{{ route('invitations.manage.index') }}"
                            class="shrink-0 text-xs text-brand-400 hover:text-brand-300 border border-white/10 rounded-lg px-3 py-2 transition">
                            View all invitations
                        </a>
                    </div>

                    <form method="POST" action="{{ route('invitations.owner.store') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Admin email</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                required
                                class="w-full rounded-xl border border-white/10 bg-surface-300 px-4 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                                placeholder="admin@example.com"
                            >
                        </div>

                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600"
                        >
                            Send owner invitation
                        </button>
                    </form>
            </div>

            <div class="min-w-0 rounded-2xl border border-white/5 bg-surface-200 shadow-xl shadow-black/10 overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-white/5 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Tenants</h2>
                        <p class="text-sm text-slate-400">Public info overview.</p>
                    </div>
                    @include('partials.sort-control', [
                        'param' => 'tsort',
                        'dirParam' => 'tdir',
                        'options' => [
                            'id:asc' => 'ID A–Z',
                            'created_at:desc' => 'Newest',
                            'created_at:asc' => 'Oldest',
                            'name:asc' => 'Name A–Z',
                            'users_count:desc' => 'Most users',
                        ],
                    ])
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-0 text-sm">
                            <thead class="bg-white/3 text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">#</th>
                                    <th class="px-4 py-3 text-left font-medium">Name</th>
                                    <th class="px-4 py-3 text-left font-medium">Admin Email</th>
                                    <th class="px-4 py-3 text-left font-medium">Users</th>
                                    <th class="px-4 py-3 text-left font-medium">Groups</th>
                                    <th class="px-4 py-3 text-left font-medium">Status</th>
                                    <th class="px-4 py-3 text-left font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse ($tenants as $tenant)
                                    <tr class="text-slate-300"
                                        x-show="ownerMatch(@js(strtolower(($tenant->name ?? '').' '.($tenant->admin_email ?? '').' '.($tenant->slug ?? '').' '.$tenant->id)))">
                                        <td class="px-4 py-3">{{ $tenant->id }}</td>
                                        <td class="px-4 py-3 font-medium text-white">{{ $tenant->name }}</td>
                                        <td class="px-4 py-3">{{ $tenant->admin_email }}</td>
                                        <td class="px-4 py-3">{{ $tenant->users_count }}</td>
                                        <td class="px-4 py-3">{{ $tenant->groups_count }}</td>
                                        <td class="px-4 py-3">
                                            @if ($tenant->isClosed())
                                                <span class="inline-flex rounded-full border border-red-500/20 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-300">
                                                    Closed
                                                </span>
                                                @if ($tenant->closure?->closedBy)
                                                    <span class="block text-[10px] text-slate-500 mt-0.5">
                                                        by {{ $tenant->closure->closedBy->displayLabel() }}
                                                    </span>
                                                @endif
                                            @else
                                                <span class="inline-flex rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-300">
                                                    Active
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($tenant->id === 1)
                                                <span class="text-xs text-slate-600">—</span>
                                            @elseif ($tenant->isClosed())
                                                <div class="flex items-center gap-3">
                                                    <form method="POST" action="{{ route('owner.tenants.reopen', $tenant) }}">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="text-xs text-emerald-400 hover:text-emerald-300">Reopen</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('owner.tenants.destroy', $tenant) }}">
                                                        @csrf @method('DELETE')
                                                        <button type="button" @click="$dispatch('confirm-action', { message: 'Permanently remove the workspace ' + @js($tenant->name) + ' and all its data?', form: $el.closest('form') })"
                                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500/80 hover:text-red-400 hover:bg-red-500/10 px-2 py-1 rounded-lg transition">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            Remove</button>
                                                    </form>
                                                </div>
                                            @else
                                                <div class="flex items-center gap-3">
                                                    <form method="POST" action="{{ route('owner.tenants.close', $tenant) }}">
                                                        @csrf
                                                        <button type="submit" class="text-xs text-red-400 hover:text-red-300">Close</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('owner.tenants.destroy', $tenant) }}">
                                                        @csrf @method('DELETE')
                                                        <button type="button" @click="$dispatch('confirm-action', { message: 'Permanently remove the workspace ' + @js($tenant->name) + ' and all its data?', form: $el.closest('form') })"
                                                            class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500/80 hover:text-red-400 hover:bg-red-500/10 px-2 py-1 rounded-lg transition">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                            Remove</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No tenants found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
        </section>

        <section class="rounded-2xl border border-white/5 bg-surface-200 shadow-xl shadow-black/10 overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-white/5 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Users</h2>
                    <p class="text-sm text-slate-400">Users with tenant and ban information.</p>
                </div>
                @include('partials.sort-control', [
                    'param' => 'usort',
                    'dirParam' => 'udir',
                    'options' => [
                        'id:asc' => 'ID A–Z',
                        'created_at:desc' => 'Newest',
                        'created_at:asc' => 'Oldest',
                        'display_name:asc' => 'Name A–Z',
                        'email:asc' => 'Email A–Z',
                        'banned:desc' => 'Banned first',
                    ],
                ])
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/3 text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">#</th>
                            <th class="px-4 py-3 text-left font-medium">Display Name</th>
                            <th class="px-4 py-3 text-left font-medium">Username</th>
                            <th class="px-4 py-3 text-left font-medium">Email</th>
                            <th class="px-4 py-3 text-left font-medium">Tenant</th>
                            <th class="px-4 py-3 text-left font-medium">Role</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-left font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($users as $user)
                            <tr class="text-slate-300"
                                x-show="ownerMatch(@js(strtolower(($user->display_name ?? '').' '.($user->username ?? '').' '.($user->email ?? '').' '.($user->tenant?->name ?? '').' '.($user->tenantRole?->name ?? '').' '.$user->id)))">
                                <td class="px-4 py-3">{{ $user->id }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold shrink-0"
                                            :style="'background-color: ' + @js($user->avatarColor())">
                                            {{ $user->avatarInitial() }}
                                        </div>
                                        <span class="font-medium text-white">{{ $user->display_name ?? $user->email }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ $user->username }}</td>
                                <td class="px-4 py-3">{{ $user->email }}</td>
                                <td class="px-4 py-3">{{ $user->tenant?->name ?? 'Owner / None' }}</td>
                                <td class="px-4 py-3">{{ $user->tenantRole?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->banned_by_id)
                                        <span class="inline-flex rounded-full border border-red-500/20 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-300">
                                            Banned
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-300">
                                            Active
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if(!$user->isOwner())
                                        <div class="flex items-center gap-3">
                                            @if($user->banned_by_id)
                                                <form method="POST" action="{{ route('owner.users.unban', $user) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="inline-block w-14 text-center text-xs text-emerald-400 hover:text-emerald-300">Unban</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('owner.users.ban', $user) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-block w-14 text-center text-xs text-red-400 hover:text-red-300">Ban</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('owner.users.destroy', $user) }}">
                                                @csrf @method('DELETE')
                                                <button type="button" @click="$dispatch('confirm-action', { message: 'Permanently remove ' + @js($user->displayLabel()) + '? Their messages and call history will be kept.', form: $el.closest('form') })"
                                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500/80 hover:text-red-400 hover:bg-red-500/10 px-2 py-1 rounded-lg transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-600">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-slate-500">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-8 2xl:grid-cols-2">
            <div class="rounded-2xl border border-white/5 bg-surface-200 p-6 shadow-xl shadow-black/10">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Groups</h2>
                        <p class="text-sm text-slate-400">Group public info and members.</p>
                    </div>
                    @include('partials.sort-control', [
                        'param' => 'gsort',
                        'dirParam' => 'gdir',
                        'options' => [
                            'id:asc' => 'ID A–Z',
                            'created_at:desc' => 'Newest',
                            'created_at:asc' => 'Oldest',
                            'name:asc' => 'Name A–Z',
                            'members_count:desc' => 'Most members',
                        ],
                    ])
                </div>

                <div class="space-y-4">
                    @forelse ($groups as $group)
                        <div class="rounded-2xl border border-white/5 bg-surface-300 p-4"
                            x-show="ownerMatch(@js(strtolower(($group->name ?? '').' '.($group->tenant?->name ?? '').' '.$group->members->map(fn ($m) => $m->display_name ?? $m->username)->implode(' '))))">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-medium text-white">{{ $group->name }}</h3>
                                    <p class="text-sm text-slate-400">
                                        Tenant: {{ $group->tenant?->name ?? '—' }} · Members: {{ $group->members_count }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($group->members as $member)
                                    <span class="rounded-full border border-white/5 bg-white/3 px-3 py-1 text-xs text-slate-300">
                                        {{ $member->display_name ?? $member->username }}
                                    </span>
                                @empty
                                    <span class="text-sm text-slate-500">No active members.</span>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No groups found.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-white/5 bg-surface-200 p-6 shadow-xl shadow-black/10">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-white">Duos</h2>
                    <p class="text-sm text-slate-400">Duo names and the paired users.</p>
                </div>

                <div class="space-y-4">
                    @forelse ($duos as $duo)
                        <div class="rounded-2xl border border-white/5 bg-surface-300 p-4"
                            x-show="ownerMatch(@js(strtolower(($duo->name ?? '').' '.($duo->group?->name ?? '').' '.($duo->user1?->display_name ?? $duo->user1?->username ?? '').' '.($duo->user2?->display_name ?? $duo->user2?->username ?? ''))))">
                            <h3 class="font-medium text-white">{{ $duo->name }}</h3>
                            <p class="mt-1 text-sm text-slate-400">Group: {{ $duo->group?->name ?? '—' }}</p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="rounded-full border border-white/5 bg-white/3 px-3 py-1 text-xs text-slate-300">
                                    {{ $duo->user1?->display_name ?? $duo->user1?->username ?? 'Unknown user' }}
                                </span>
                                <span class="rounded-full border border-white/5 bg-white/3 px-3 py-1 text-xs text-slate-300">
                                    {{ $duo->user2?->display_name ?? $duo->user2?->username ?? 'Unknown user' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No duos found.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection
