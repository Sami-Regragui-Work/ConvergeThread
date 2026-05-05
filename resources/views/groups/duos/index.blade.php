@extends('layouts.app')
@section('title', 'Duos — ' . $group->name)

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ url('/groups/' . $group->id) }}"
                class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h1 class="text-xl font-bold text-white">Duos</h1>
            <span class="text-xs text-slate-500">{{ $group->name }}</span>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($duos as $duo)
                    <a href="{{ url('/groups/' . $group->id . '/duos/' . $duo->id) }}"
                        class="flex items-center gap-4 px-5 py-4 hover:bg-white/5 transition group">
                        <div class="flex -space-x-2 shrink-0">
                            @foreach([$duo->userA, $duo->userB] as $u)
                                <div
                                    class="w-8 h-8 rounded-full bg-brand-500/10 border-2 border-surface-200 text-brand-400 flex items-center justify-center text-xs font-semibold">
                                    {{ strtoupper(substr($u->display_name ?? $u->email, 0, 1)) }}
                                </div>
                            @endforeach
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white font-medium">
                                {{ $duo->userA->display_name ?? $duo->userA->email }}
                                <span class="text-slate-500">&</span>
                                {{ $duo->userB->display_name ?? $duo->userB->email }}
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-slate-600 group-hover:text-slate-400 transition" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No duos in this group yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection