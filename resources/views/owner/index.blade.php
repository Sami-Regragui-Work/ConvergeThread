@extends('layouts.app')

@section('title', 'Owner Dashboard')

@section('content')
    <div class="space-y-8">
        <section class="space-y-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Owner Dashboard</h1>
                <p class="text-sm text-slate-600">Bootstrap tenant admins, inspect platform data, and review current counts.
                </p>
            </div>

            @if (session('success'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Users</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['users_count'] }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Banned Users</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['banned_users_count'] }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Tenants</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['tenants_count'] }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Closed Tenants</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['closed_tenants_count'] }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Groups</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['groups_count'] }}</p>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-sm text-slate-500">Duos</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $stats['duos_count'] }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Invite Tenant Admin</h2>
                <p class="text-sm text-slate-600">This uses the existing owner invitation flow.</p>
            </div>

            <form method="POST" action="{{ route('invitations.owner.store') }}" class="grid gap-4 md:grid-cols-2">
                @csrf

                <div class="md:col-span-2">
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Admin email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-slate-500 focus:outline-none">
                </div>

                <div class="md:col-span-2">
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        Send owner invitation
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Tenants</h2>
                <p class="text-sm text-slate-600">Public info overview.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">#</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Name</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Admin Email</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Users</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Groups</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($tenants as $tenant)
                            <tr>
                                <td class="px-4 py-3 text-slate-600">{{ $tenant->id }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $tenant->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $tenant->admin_email }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $tenant->users_count }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $tenant->groups_count }}</td>
                                <td class="px-4 py-3">
                                    @if ($tenant->closed_by_id)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                            Closed
                                        </span>
                                    @else
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                            Active
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-slate-500">No tenants found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Users</h2>
                <p class="text-sm text-slate-600">Users with tenant and ban information.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">#</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Display Name</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Username</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Email</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Tenant</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Role</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-700">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-4 py-3 text-slate-600">{{ $user->id }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    {{ $user->display_name ?? $user->email }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $user->username }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $user->tenant?->name ?? 'Owner / None' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $user->tenantRole?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->banned_by_id)
                                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                            Banned
                                        </span>
                                    @else
                                        <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                            Active
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-4 text-center text-slate-500">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Groups</h2>
                <p class="text-sm text-slate-600">Group public info and members.</p>
            </div>

            <div class="space-y-4">
                @forelse ($groups as $group)
                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h3 class="font-medium text-slate-900">{{ $group->name }}</h3>
                                <p class="text-sm text-slate-600">
                                    Tenant: {{ $group->tenant?->name ?? '—' }} · Members: {{ $group->users_count }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse ($group->users as $member)
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
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
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Duos</h2>
                <p class="text-sm text-slate-600">Duo names and the paired users.</p>
            </div>

            <div class="space-y-4">
                @forelse ($duos as $duo)
                    <div class="rounded-md border border-slate-200 p-4">
                        <h3 class="font-medium text-slate-900">{{ $duo->name }}</h3>
                        <p class="mt-1 text-sm text-slate-600">Group: {{ $duo->group?->name ?? '—' }}</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                                {{ $duo->user1?->display_name ?? $duo->user1?->username ?? 'Unknown user' }}
                            </span>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                                {{ $duo->user2?->display_name ?? $duo->user2?->username ?? 'Unknown user' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No duos found.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection