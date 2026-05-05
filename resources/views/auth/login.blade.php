@extends('layouts.app')
@section('title', 'Sign In')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-brand-500 flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">
                    CT</div>
                <h1 class="text-2xl font-bold text-white">Welcome back</h1>
                <p class="text-slate-400 text-sm mt-1">Sign in to your workspace</p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ url('/auth/login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="you@example.com">
                        @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                        <input type="password" name="password" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="••••••••">
                        @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                        Sign in
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