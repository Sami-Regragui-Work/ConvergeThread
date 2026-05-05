@extends('layouts.app')
@section('title', 'Merge Sessions')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-white">Merge Sessions</h1>
            <a href="{{ url('/merge-sessions/create') }}"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                + New Session
            </a>
        </div>

        @if($sessions->isEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl p-12 text-center">
                <p class="text-slate-400 text-sm">No merge sessions yet.</p>
            </div>
        @else
            <div class="grid gap-3">
                @foreach($sessions as $session)
                    <a href="{{ url('/merge-sessions/' . $session->id) }}"
                        class="bg-surface-200 border border-white/5 rounded-2xl px-5 py-4 flex items-center gap-4 hover:border-white/10 transition group">
                        <div class="w-10 h-10 rounded-xl bg-brand-500/10 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-white font-medium text-sm">Session #{{ $session->id }}</p>
                            <p class="text-slate-500 text-xs">{{ $session->groupA->name ?? '?' }} +
                                {{ $session->groupB->name ?? '?' }}</p>
                        </div>
                        <svg class="w-4 h-4 text-slate-600 group-hover:text-slate-400 transition" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection