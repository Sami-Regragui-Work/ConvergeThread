@extends('layouts.app')
@section('title', $mergeSession->name)

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('merge-sessions.index') }}"
                class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div class="flex-1">
                <h1 class="text-xl font-bold text-white">{{ $mergeSession->name }}</h1>
                <p class="text-xs text-slate-500">Active merge session</p>
            </div>
            @can('delete', $mergeSession)
                <form method="POST" action="{{ route('merge-sessions.destroy', $mergeSession) }}">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('End this merge session?')"
                        class="text-xs text-red-400 hover:text-red-300 transition px-3 py-1.5 rounded-lg hover:bg-red-500/10">
                        End Session
                    </button>
                </form>
            @endcan
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl p-6">
            <p class="text-sm text-slate-400 mb-4">Merged groups share a temporary chat space.</p>
            <a href="{{ route('messages.index', ['merge', $mergeSession->id]) }}"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
                Open merged chat
            </a>
        </div>
    </div>
@endsection
