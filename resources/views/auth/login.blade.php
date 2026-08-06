@extends('layouts.app')
@section('title', 'Sign In')

@section('content')
    <div class="min-h-full flex items-center justify-center" x-data="loginForm({
        action: @js(url('/auth/login')),
        publicKeyUrl: @js(route('messages.crypto.public-key')),
        storeBackupUrl: @js(route('messages.crypto.backup.store')),
    })">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-brand-500 flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">
                    CT</div>
                <h1 class="text-2xl font-bold text-white">Welcome back</h1>
                <p class="text-slate-400 text-sm mt-1">Sign in to your workspace</p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ url('/auth/login') }}" class="space-y-5" @submit.prevent="submit()">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" name="email" x-model="email" value="{{ old('email') }}" required autofocus
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="you@example.com">
                        @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                        <input type="password" name="password" x-model="password" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="••••••••">
                        @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>
                    <p x-show="status" x-cloak class="text-xs text-slate-400" x-text="status"></p>

                    <button type="submit" :disabled="busy"
                        class="w-full bg-brand-500 hover:bg-brand-600 disabled:opacity-50 text-white font-semibold py-2.5 rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                        <span x-text="busy ? 'Signing in…' : 'Sign in'"></span>
                    </button>
                </form>

                <p class="text-center text-sm text-slate-500 mt-6">
                    Don't have an account?
                    <a href="{{ url('/auth/register') }}"
                        class="text-brand-400 hover:text-brand-300 transition">Register</a>
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
@include('partials.chat-crypto')
<script>
    function loginForm(config) {
        return {
            email: @js(old('email', '')),
            password: '',
            busy: false,
            error: '',
            status: '',
            async submit() {
                this.busy = true;
                this.error = '';
                this.status = 'Signing in…';
                try {
                    const res = await fetch(config.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            email: this.email,
                            password: this.password,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.error = data.message || data.errors?.email?.[0] || 'Sign in failed.';
                        this.busy = false;
                        this.status = '';
                        return;
                    }

                    if (window.ChatCrypto && data.user_id && this.password) {
                        this.status = 'Unlocking your E2EE key…';
                        try {
                            await window.ChatCrypto.syncAccountIdentity(data.user_id, this.password, {
                                backup: data.e2ee_backup,
                                backupUrl: config.storeBackupUrl,
                                publicKeyUrl: config.publicKeyUrl,
                            });
                        } catch (e) {
                            console.warn(e);
                        }
                    }

                    this.password = '';
                    window.location.href = data.redirect || @js(url('/groups'));
                } catch (e) {
                    this.error = 'Network error. Try again.';
                    this.status = '';
                    this.busy = false;
                }
            },
        };
    }
</script>
@endpush
