@extends('layouts.app')
@section('title', 'Invite Member')

@section('content')
    <div class="max-w-lg mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ url('/groups/' . $group->id . '/members') }}"
                class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h1 class="text-xl font-bold text-white">Invite Member</h1>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
            <form method="POST" action="{{ url('/invitations/tenant') }}" class="space-y-5">
                @csrf

                <input type="hidden" name="group_id" value="{{ $group->id }}">

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="colleague@example.com">
                    @error('email')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Tenant Role <span
                            class="text-slate-500">(optional)</span></label>
                    <select name="tenant_role_id"
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                        <option value="">— None —</option>
                        @foreach($tenantRoles ?? [] as $role)
                            <option value="{{ $role->id }}" {{ old('tenant_role_id') == $role->id ? 'selected' : '' }}>
                                {{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_role_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ url('/groups/' . $group->id . '/members') }}"
                        class="flex-1 text-center bg-white/5 hover:bg-white/10 text-slate-300 font-semibold py-2.5 rounded-xl text-sm transition">
                        Cancel
                    </a>
                    <button type="submit"
                        class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                        Send Invite
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
