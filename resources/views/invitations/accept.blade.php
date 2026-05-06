@extends('layouts.app')
@section('title', 'Accept Invitation')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md" x-data="{ step: 1 }">

            @if(is_null($invitation->tenant_id))
                <div class="flex items-center justify-center gap-3 mb-6">
                    <span class="flex items-center gap-1.5 text-xs font-medium"
                        :class="step === 1 ? 'text-brand-400' : 'text-slate-500'">
                        <span class="w-5 h-5 rounded-full border flex items-center justify-center text-[10px]"
                            :class="step === 1 ? 'border-brand-400 text-brand-400' : 'border-slate-600 text-slate-500'">1</span>
                        Your account
                    </span>
                    <span class="w-6 h-px bg-slate-700"></span>
                    <span class="flex items-center gap-1.5 text-xs font-medium"
                        :class="step === 2 ? 'text-brand-400' : 'text-slate-500'">
                        <span class="w-5 h-5 rounded-full border flex items-center justify-center text-[10px]"
                            :class="step === 2 ? 'border-brand-400 text-brand-400' : 'border-slate-600 text-slate-500'">2</span>
                        Workspace
                    </span>
                </div>
            @endif

            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-white">
                    <span x-show="step === 1">Complete your profile</span>
                    @if(is_null($invitation->tenant_id))
                        <span x-show="step === 2" x-cloak>Set up your workspace</span>
                    @endif
                </h1>
                <p class="text-slate-400 text-sm mt-1">
                    <span x-show="step === 1">Set a password to activate your account</span>
                    @if(is_null($invitation->tenant_id))
                        <span x-show="step === 2" x-cloak>Give your workspace a name</span>
                    @endif
                </p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ url('/invitations/' . $invitation->token . '/accept') }}" class="space-y-5">
                    @csrf

                    @if(is_null($invitation->tenant_id))
                        <input type="hidden" name="is_admin_invite" value="1">
                    @endif

                    {{-- Step 1 --}}
                    <div x-show="step === 1" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                            <input type="email" value="{{ $invitation->email }}" disabled
                                class="w-full bg-surface-300/50 border border-white/5 text-slate-400 rounded-xl px-4 py-2.5 text-sm cursor-not-allowed">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-1.5">
                                Display Name <span class="text-slate-500">(optional)</span>
                            </label>
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

                        @if(is_null($invitation->tenant_id))
                            <button type="button" @click="step = 2"
                                class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                                Continue
                            </button>
                        @else
                            <button type="submit"
                                class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                                Activate Account
                            </button>
                        @endif
                    </div>

                    {{-- Step 2 (admin invite only) --}}
                    @if(is_null($invitation->tenant_id))
                        <div x-show="step === 2" x-cloak class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1.5">Workspace Name</label>
                                <input type="text" name="tenant_name" value="{{ old('tenant_name') }}" required
                                    class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                                    placeholder="Acme Corp">
                                @error('tenant_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex gap-3 pt-1">
                                <button type="button" @click="step = 1"
                                    class="flex-1 bg-surface-300 hover:bg-surface-400 border border-white/10 text-slate-300 font-semibold py-2.5 rounded-xl text-sm transition">
                                    Back
                                </button>
                                <button type="submit"
                                    class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                                    Create Workspace
                                </button>
                            </div>
                        </div>
                    @endif

                </form>
            </div>

        </div>
    </div>
@endsection