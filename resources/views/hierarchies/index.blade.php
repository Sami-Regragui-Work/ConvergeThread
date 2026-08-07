@extends('layouts.app')
@section('title', 'Role Hierarchies')

@section('content')
    <div class="max-w-5xl mx-auto space-y-6" data-sync="hierarchies,members">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-white">Role Hierarchies</h1>
                <p class="text-sm text-slate-500 mt-1">Separate chains (e.g. Engineering vs Finance). Level 0 is the top.</p>
            </div>
            <form method="POST" action="{{ route('hierarchies.store') }}" class="flex gap-2">
                @csrf
                <input type="text" name="name" required placeholder="New hierarchy name"
                    class="bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2 text-sm min-w-48 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                <button type="submit" class="bg-brand-500 hover:bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-semibold">Create</button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-3 bg-surface-200 border border-white/5 rounded-2xl p-4 max-h-80 overflow-y-auto">
                <h2 class="text-sm font-semibold text-white mb-3">Workspace members</h2>
                <div class="space-y-1.5">
                    @foreach($members as $member)
                        <div class="text-xs text-slate-400 px-1 py-0.5 truncate">{{ $member->displayLabel() }}</div>
                    @endforeach
                </div>
            </div>

            <div class="lg:col-span-9 space-y-4">
                @forelse($hierarchies as $hierarchy)
                    <div class="bg-surface-200 border border-white/5 rounded-2xl">
                        <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-white">{{ $hierarchy->name }}</h2>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('hierarchies.levels.store', $hierarchy) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-brand-400 hover:text-brand-300">+ Level</button>
                                </form>
                                <form method="POST" action="{{ route('hierarchies.destroy', $hierarchy) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-300">Delete</button>
                                </form>
                            </div>
                        </div>
                        <div class="divide-y divide-white/5">
                            @foreach($hierarchy->levels as $level)
                                <div class="px-5 py-4">
                                    <div class="flex items-center justify-between gap-3 mb-3">
                                        <p class="text-xs text-slate-500">
                                            @if($level->level === 0)
                                                Level 0 — Top level
                                            @else
                                                Level {{ $level->level }}
                                            @endif
                                        </p>
                                        @if($level->level > 0)
                                            <form method="POST" action="{{ route('hierarchies.levels.destroy', $level) }}"
                                                @submit.prevent="$dispatch('confirm-action', { message: 'Remove this level?', form: $event.target })">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-xs text-red-400 hover:text-red-300">Remove level</button>
                                            </form>
                                        @endif
                                    </div>
                                    @php
                                        $pickerMembers = $members->map(fn ($m) => [
                                            'id' => $m->id,
                                            'display_name' => $m->displayLabel(),
                                            'username' => $m->username,
                                        ])->values();
                                    @endphp
                                    <form method="POST" action="{{ route('hierarchies.levels.members', $level) }}">
                                        @csrf @method('PATCH')
                                        @include('partials.member-picker', [
                                            'members' => $pickerMembers,
                                            'selected' => $level->members->pluck('id')->all(),
                                            'name' => 'user_ids',
                                            'direction' => 'dropdown',
                                        ])
                                        <button type="submit" class="mt-3 text-xs text-brand-400 hover:text-brand-300">Save level members</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="bg-surface-200 border border-white/5 rounded-2xl p-10 text-center text-slate-500 text-sm">
                        No hierarchies yet. Create one to define who manages whom.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
