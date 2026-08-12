@extends('layouts.app')
@section('title', 'Calls')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-bold text-white">Calls</h1>
            @include('partials.sort-control', [
                'label' => 'Sort',
                'options' => [
                    'started_at:desc' => 'Recent',
                    'started_at:asc' => 'Oldest',
                    'call_type:asc' => 'Call type A–Z',
                    'status:asc' => 'Status A–Z',
                    'total_duration:desc' => 'Longest',
                ],
            ])
        </div>

        @if($logs->isEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl p-12 text-center">
                <div class="w-12 h-12 rounded-xl bg-white/5 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </div>
                <p class="text-slate-400 text-sm">No calls yet. Start one from any chat.</p>
            </div>
        @else
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden divide-y divide-white/5">
                @foreach($logs as $log)
                    <a href="{{ route('calls.show', $log) }}"
                        class="px-5 py-4 flex items-center gap-4 hover:bg-white/3 transition group">
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $log->call_type === 'video' ? 'bg-violet-500/10' : 'bg-brand-500/10' }}">
                            <svg class="w-5 h-5 {{ $log->call_type === 'video' ? 'text-violet-400' : 'text-brand-400' }}" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                @if($log->call_type === 'video')
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                @endif
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white truncate">
                                <span class="font-semibold">{{ $log->caller?->displayLabel() ?? 'Former member' }}</span>
                                <span class="text-slate-500">
                                    → {{ $log->call_type === 'video' ? 'Video' : 'Voice' }} call
                                    in <span class="text-slate-300 font-medium">{{ $log->chat_label }}</span>
                                </span>
                            </p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ $log->started_at?->diffForHumans() }}
                                @if($log->ended_at)
                                    · {{ gmdate('i:s', $log->total_duration ?? 0) }}
                                @endif
                            </p>
                        </div>
                        @php
                            $badge = match ($log->status) {
                                'ongoing' => ['Ongoing', 'bg-amber-500/10 text-amber-400'],
                                'completed' => ['Completed', 'bg-emerald-500/10 text-emerald-400'],
                                'missed' => ['Missed', 'bg-red-500/10 text-red-400'],
                                'declined' => ['Declined', 'bg-slate-500/10 text-slate-400'],
                                default => [$log->status, 'bg-white/5 text-slate-400'],
                            };
                        @endphp
                        <span class="shrink-0 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge[1] }}">
                            {{ $badge[0] }}
                        </span>
                        <svg class="w-4 h-4 text-slate-600 group-hover:text-slate-400 transition shrink-0" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endforeach
            </div>

            <div>
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection
