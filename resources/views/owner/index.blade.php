@extends('layouts.app')

@section('title', 'Owner Dashboard')

@section('content')
    <div class="space-y-8">
        <section class="space-y-4">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">Owner Dashboard</h1>
                    <p class="text-sm text-slate-400">
                        Bootstrap tenant admins, inspect platform data, and review current counts.
                    </p>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                    <p class="mb-2 font-semibold text-red-200">Please fix the following errors:</p>
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
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
        </section>

        <section class="grid gap-8 xl:grid-cols-3">
            <div class="xl:col-span-1">
                <div class="rounded-2xl border border-white/5 bg-surface-200 p-6 shadow-xl shadow-black/10">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-white">Invite Tenant Admin</h2>
                        <p class="mt-1 text-sm text-slate-400">This uses the existing owner invitation flow.</p>
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
                                class="w-full rounded-xl border border-white/10 bg-surface-300 px-4 py-2.5 text-sm text-white placeholder:text-slate-500 focus:border-brand-500/50 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
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
            </div>

            <div class="xl:col-span-2">
                <div class="rounded-2xl border border-white/5 bg-surface-200 shadow-xl shadow-black/10 overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-white/5 px-6 py-4">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Tenants</h2>
                            <p class="text-sm text-slate-400">Public info overview.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-white/[0.03] text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">#</th>
                                    <th class="px-4 py-3 text-left font-medium">Name</th>
                                    <th class="px-4 py-3 text-left font-medium">Admin Email</th>
                                    <th class="px-4 py-3 text-left font-medium">Users</th>
                                    <th class="px-4 py-3 text-left font-medium">Groups</th>
                                    <th class="px-4 py-3 text-left font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse ($tenants as $tenant)
                                    <tr class="text-slate-300">
                                        <td class="px-4 py-3">{{ $tenant->id }}</td>
                                        <td class="px-4 py-3 font-medium text-white">{{ $tenant->name }}</td>
                                        <td class="px-4 py-3">{{ $tenant->admin_email }}</td>
                                        <td class="px-4 py-3">{{ $tenant->users_count }}</td>
                                        <td class="px-4 py-3">{{ $tenant->groups_count }}</td>
                                        <td class="px-4 py-3">
                                            @if ($tenant->closed_by_id)
                                                <span class="inline-flex rounded-full border border-red-500/20 bg-red-500/10 px-2.5 py-1 text-xs font-medium text-red-300">
                                                    Closed
                                                </span>
                                            @else
                                                <span class="inline-flex rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-300">
                                                    Active
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No tenants found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-white/5 bg-surface-200 shadow-xl shadow-black/10 overflow-hidden">
            <div class="border-b border-white/5 px-6 py-4">
                <h2 class="text-lg font-semibold text-white">Users</h2>
                <p class="text-sm text-slate-400">Users with tenant and ban information.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/[0.03] text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">#</th>
                            <th class="px-4 py-3 text-left font-medium">Display Name</th>
                            <th class="px-4 py-3 text-left font-medium">Username</th>
                            <th class="px-4 py-3 text-left font-medium">Email</th>
                            <th class="px-4 py-3 text-left font-medium">Tenant</th>
                            <th class="px-4 py-3 text-left font-medium">Role</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @forelse ($users as $user)
                            <tr class="text-slate-300">
                                <td class="px-4 py-3">{{ $user->id }}</td>
                                <td class="px-4 py-3 font-medium text-white">
                                    {{ $user->display_name ?? $user->email }}
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-slate-500">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-8 2xl:grid-cols-2">
            <div class="rounded-2xl border border-white/5 bg-surface-200 p-6 shadow-xl shadow-black/10">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold text-white">Groups</h2>
                    <p class="text-sm text-slate-400">Group public info and members.</p>
                </div>

                <div class="space-y-4">
                    @forelse ($groups as $group)
                        <div class="rounded-2xl border border-white/5 bg-surface-300 p-4">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-medium text-white">{{ $group->name }}</h3>
                                    <p class="text-sm text-slate-400">
                                        Tenant: {{ $group->tenant?->name ?? '—' }} · Members: {{ $group->users_count }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @forelse ($group->members as $member)
                                    <span class="rounded-full border border-white/5 bg-white/[0.03] px-3 py-1 text-xs text-slate-300">
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
                        <div class="rounded-2xl border border-white/5 bg-surface-300 p-4">
                            <h3 class="font-medium text-white">{{ $duo->name }}</h3>
                            <p class="mt-1 text-sm text-slate-400">Group: {{ $duo->group?->name ?? '—' }}</p>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="rounded-full border border-white/5 bg-white/[0.03] px-3 py-1 text-xs text-slate-300">
                                    {{ $duo->user1?->display_name ?? $duo->user1?->username ?? 'Unknown user' }}
                                </span>
                                <span class="rounded-full border border-white/5 bg-white/[0.03] px-3 py-1 text-xs text-slate-300">
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
