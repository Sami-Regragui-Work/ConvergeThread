@extends('layouts.app')
@section('title', $group->name)

@section('content')
    <div class="max-w-5xl mx-auto space-y-6">
        {{-- Header --}}
        <div
            class="bg-surface-200 border border-white/5 rounded-2xl px-6 py-5 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex items-center gap-4 flex-1">
                <div
                    class="w-12 h-12 rounded-xl bg-brand-500/10 text-brand-400 flex items-center justify-center font-bold text-base shrink-0">
                    {{ strtoupper(substr($group->name, 0, 2)) }}
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white">{{ $group->name }}</h1>
                    <p class="text-slate-500 text-xs">{{ $group->members->count() }} members</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ url('/groups/' . $group->id . '/edit') }}"
                    class="inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 text-slate-300 text-sm px-4 py-2 rounded-xl transition">
                    Edit
                </a>
                <form method="POST" action="{{ url('/groups/' . $group->id) }}">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this group?')"
                        class="inline-flex items-center gap-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm px-4 py-2 rounded-xl transition">
                        Delete
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Members --}}
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white">Members</h2>
                    <a href="{{ url('/groups/' . $group->id . '/members/create') }}"
                        class="text-xs text-brand-400 hover:text-brand-300 transition">+ Add</a>
                </div>
                <div class="divide-y divide-white/5">
                    @forelse($group->members as $member)
                        <div class="px-5 py-3 flex items-center gap-3">
                            <div
                                class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0">
                                {{ strtoupper(substr($member->user->display_name ?? $member->user->email, 0, 1)) }}
                            </div>
                            <span
                                class="text-sm text-slate-300 truncate">{{ $member->user->display_name ?? $member->user->email }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">No members yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Duos --}}
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white">Duos</h2>
                    <a href="{{ url('/groups/' . $group->id . '/duos') }}"
                        class="text-xs text-brand-400 hover:text-brand-300 transition">View all</a>
                </div>
                <div class="divide-y divide-white/5">
                    @forelse($group->duos ?? [] as $duo)
                        <a href="{{ url('/groups/' . $group->id . '/duos/' . $duo->id) }}"
                            class="block px-5 py-3 hover:bg-white/5 transition">
                            <p class="text-sm text-slate-300">Duo #{{ $duo->id }}</p>
                        </a>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">No duos yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection