@extends('layouts.app')
@section('title', 'Forgot Password')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-brand-500 flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">
                    CT</div>
                <h1 class="text-2xl font-bold text-white">Reset your password</h1>
                <p class="text-slate-400 text-sm mt-1">We'll email you a password reset link</p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ url('/auth/forgot-password') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="you@example.com">
                        @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    @if (session('status'))
                        <p class="text-xs text-emerald-400">{{ session('status') }}</p>
                    @endif

                    <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                        Send reset link
                    </button>
                </form>

                <p class="text-center text-sm text-slate-500 mt-6">
                    Remembered it?
                    <a href="{{ url('/auth/login') }}"
                        class="text-brand-400 hover:text-brand-300 transition">Sign in</a>
                </p>
            </div>
        </div>
    </div>
@endsection
