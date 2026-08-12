@extends('layouts.app')
@section('title', 'Profile')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6" data-sync="users">
        <div>
            <h1 class="text-xl font-bold text-white">Profile</h1>
            <p class="text-sm text-slate-500 mt-1">Manage your name, username, email and password.</p>
        </div>

        {{-- Identity card --}}
        <div class="bg-surface-200 border border-white/5 rounded-2xl p-6 space-y-5"
            x-data="{ profileColor: @js($user->avatar_color ?? $user->avatarColor()) }">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full flex items-center justify-center text-xl font-bold text-white shrink-0"
                    :style="'background-color: ' + profileColor">
                    {{ $user->avatarInitial() }}
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-semibold text-white truncate">{{ $user->displayLabel() }}</p>
                    <p class="text-sm text-slate-500 truncate">{{ $user->email }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Display Name</label>
                    <input type="text" name="display_name" value="{{ old('display_name', $user->display_name) }}"
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="Jane Doe">
                    @error('display_name')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Username</label>
                    <input type="text" name="username" value="{{ old('username', $user->username) }}"
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="jane_doe">
                    @error('username')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Avatar Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="avatar_color" x-model="profileColor"
                            class="h-10 w-16 rounded-lg border border-white/10 bg-surface-300 cursor-pointer">
                        <span class="text-xs text-slate-500">Choose a color for your initial avatar.</span>
                    </div>
                    @error('avatar_color')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition">
                        Save profile
                    </button>
                </div>
            </form>
        </div>

        {{-- Email --}}
        <div class="bg-surface-200 border border-white/5 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-white mb-1">Email address</h2>
            <p class="text-sm text-slate-500 mb-4">Confirm your current password to change your email.</p>
            <form method="POST" action="{{ route('profile.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">New Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="new@example.com">
                    @error('email')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Current Password</label>
                    <input type="password" name="current_password" required
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="••••••••">
                    @error('current_password')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition">
                    Request email change
                </button>
            </form>
        </div>

        {{-- Password --}}
        <div class="bg-surface-200 border border-white/5 rounded-2xl p-6">
            <h2 class="text-sm font-semibold text-white mb-1">Password</h2>
            <p class="text-sm text-slate-500 mb-4">Choose a strong password (at least 8 characters).</p>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Current Password</label>
                    <input type="password" name="current_password" required
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="••••••••">
                    @error('current_password')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">New Password</label>
                    <input type="password" name="new_password" required
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="••••••••">
                    @error('new_password')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirm New Password</label>
                    <input type="password" name="new_password_confirmation" required
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="••••••••">
                    @error('new_password_confirmation')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </div>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 transition">
                    Change password
                </button>
            </form>
        </div>

        {{-- Danger zone --}}
        @if(!$user->isOwner())
            <div class="bg-red-500/5 border border-red-500/20 rounded-2xl p-6">
                <h2 class="text-sm font-semibold text-red-300 mb-1">Danger zone</h2>
                <p class="text-sm text-slate-500 mb-4">
                    Deleting your account is permanent. Your membership in groups and duos ends, and you'll be
                    signed out. Your messages and call history are kept.
                </p>
                <form method="POST" action="{{ route('profile.destroy') }}"
                    @submit.prevent="$dispatch('confirm-action', {
                        title: 'Delete your account?',
                        message: 'This permanently deletes your account and removes you from all groups and duos.',
                        form: $el.closest('form'),
                    })">
                    @csrf @method('DELETE')
                    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                        <input type="password" name="current_password" required
                            class="flex-1 w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500/50 transition placeholder-slate-500"
                            placeholder="Confirm your current password">
                        <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-500/90 hover:bg-red-500 text-white px-4 py-2.5 text-sm font-semibold transition shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete account
                        </button>
                    </div>
                    @error('current_password')<p class="mt-2 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                </form>
            </div>
        @endif
    </div>
@endsection
