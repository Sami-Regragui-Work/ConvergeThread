@extends('layouts.app')
@section('title', 'Reset Password')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-brand-500 flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">
                    CT</div>
                <h1 class="text-2xl font-bold text-white">Choose a new password</h1>
                <p class="text-slate-400 text-sm mt-1">For {{ $email }}</p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ url('/auth/reset-password') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email', $email) }}" readonly required
                            class="w-full bg-surface-300 border border-white/10 text-slate-500 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500">
                        @error('email')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    <div x-data="{ show: false }">
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">New Password</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="password" required
                                class="w-full bg-surface-300 border border-white/10 text-white rounded-xl pl-4 pr-11 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                                placeholder="••••••••">
                            <button type="button" @click="show = !show"
                                :aria-label="show ? 'Hide password' : 'Show password'"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-200 transition">
                                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('password')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    <div x-data="{ show: false }">
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirm New Password</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="password_confirmation" required
                                class="w-full bg-surface-300 border border-white/10 text-white rounded-xl pl-4 pr-11 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                                placeholder="••••••••">
                            <button type="button" @click="show = !show"
                                :aria-label="show ? 'Hide password' : 'Show password'"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-200 transition">
                                <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        @error('password_confirmation')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    @if (session('status'))
                        <p class="text-xs text-emerald-400">{{ session('status') }}</p>
                    @endif

                    <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                        Reset password
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
