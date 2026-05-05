@extends('layouts.app')
@section('title', 'Accept Invitation')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-white">Complete your profile</h1>
                <p class="text-slate-400 text-sm mt-1">Set a password to activate your account</p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ url('/invitations/' . $invitation->token . '/accept') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" value="{{ $invitation->email }}" disabled
                            class="w-full bg-surface-300/50 border border-white/5 text-slate-400 rounded-xl px-4 py-2.5 text-sm cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Display Name <span
                                class="text-slate-500">(optional)</span></label>
                        <input type="text" name="display_name" value="{{ old('display_name') }}"
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="Jane Doe">
                        @error('display_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                        <input type="password" name="password" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="••••••••">
                        @error('password')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirm Password</label>
                        <input type="password" name="password_confirmation" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="••••••••">
                    </div>

                    @if($invitation->tenant_id === 0)
                        <div class="bg-brand-500/5 border border-brand-500/20 rounded-xl px-4 py-3 text-sm text-brand-400">
                            You are setting up the <strong>owner account</strong>. You'll be prompted to create a tenant next.
                        </div>
                    @endif

                    <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                        Activate Account
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection