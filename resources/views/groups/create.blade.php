@extends('layouts.app')
@section('title', 'New Group')

@section('content')
    <div class="max-w-lg mx-auto">
        <h1 class="text-xl font-bold text-white mb-6">New Group</h1>

        <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
            <form method="POST" action="{{ url('/groups') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Group Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required autofocus
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                        placeholder="e.g. Engineering">
                    @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ url('/groups') }}"
                        class="flex-1 text-center bg-white/5 hover:bg-white/10 text-slate-300 font-semibold py-2.5 rounded-xl text-sm transition">
                        Cancel
                    </a>
                    <button type="submit"
                        class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                        Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection