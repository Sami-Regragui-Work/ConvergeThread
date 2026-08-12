@extends('layouts.app')
@section('title', 'Call Details')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="bg-surface-200 border border-white/5 rounded-2xl px-6 py-5 flex flex-col sm:flex-row sm:items-center gap-4">
            <div
                class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 {{ $callLog->call_type === 'video' ? 'bg-violet-500/10' : 'bg-brand-500/10' }}">
                <svg class="w-6 h-6 {{ $callLog->call_type === 'video' ? 'text-violet-400' : 'text-brand-400' }}" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    @if($callLog->call_type === 'video')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    @endif
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-lg font-bold text-white">
                    {{ $callLog->call_type === 'video' ? 'Video' : 'Voice' }} call
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    in <span class="font-semibold text-slate-300">{{ $callLog->chat_label }}</span>
                    · by <span class="font-semibold text-slate-300">{{ $callLog->caller?->displayLabel() ?? 'Former member' }}</span>
                    · {{ $callLog->started_at?->format('M j, Y g:i A') }}
                </p>
            </div>
            <div class="flex flex-col items-end gap-1.5 shrink-0">
                @php
                    $badge = match ($callLog->status) {
                        'ongoing' => ['Ongoing', 'bg-amber-500/10 text-amber-400'],
                        'completed' => ['Completed', 'bg-emerald-500/10 text-emerald-400'],
                        'missed' => ['Missed', 'bg-red-500/10 text-red-400'],
                        'declined' => ['Declined', 'bg-slate-500/10 text-slate-400'],
                        default => [$callLog->status, 'bg-white/5 text-slate-400'],
                    };
                @endphp
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                @if($callLog->ended_at)
                    <span class="text-xs text-slate-400 font-medium">Duration {{ gmdate('i:s', $callLog->total_duration ?? 0) }}</span>
                @endif
            </div>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="px-5 py-3 border-b border-white/5">
                <h2 class="text-sm font-semibold text-white">Participants</h2>
            </div>
            <div class="divide-y divide-white/5">
                @foreach($callLog->participants as $participant)
                    <div class="px-5 py-3 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold shrink-0"
                            :style="'background-color: ' + @js($participant->user?->avatarColor() ?? '#64748b')">
                            {{ $participant->user?->avatarInitial() ?? '?' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white truncate">
                                {{ $participant->user?->displayLabel() ?? 'Former member' }}
                                @if($participant->role === 'caller')
                                    <span class="text-[10px] text-slate-500 font-semibold uppercase ml-1">Caller</span>
                                @endif
                            </p>
                            <p class="text-xs text-slate-500">
                                @if($participant->joined_at)
                                    Joined {{ $participant->joined_at->format('g:i A') }}
                                @elseif($participant->status === 'declined')
                                    Declined
                                @elseif($participant->status === 'missed')
                                    Missed the call
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        @if($participant->status === 'left' || $participant->status === 'joined')
                            <span class="text-xs text-slate-300 font-medium shrink-0">{{ gmdate('i:s', $participant->duration) }}</span>
                        @endif
                        <span
                            class="shrink-0 inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ match ($participant->status) {
                                'joined' => 'bg-emerald-500/10 text-emerald-400',
                                'left' => 'bg-slate-500/10 text-slate-400',
                                'declined' => 'bg-slate-500/10 text-slate-400',
                                'missed' => 'bg-red-500/10 text-red-400',
                                default => 'bg-white/5 text-slate-400',
                            } }}">
                            {{ match ($participant->status) {
                                'pending' => 'Pending',
                                'joined' => 'In call',
                                'left' => 'Left',
                                'declined' => 'Declined',
                                'missed' => 'Missed',
                                default => $participant->status,
                            } }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $events = collect();
            foreach ($callLog->participants as $participant) {
                foreach ($participant->timeline ?? [] as $event) {
                    $events->push([
                        'at' => $event['at'] ?? null,
                        'name' => $participant->user?->displayLabel() ?? 'Former member',
                        'type' => $event['type'] ?? 'joined',
                    ]);
                }
            }
            $events = $events->sortBy('at')->values();
            $eventLabels = [
                'started' => 'started the call',
                'joined' => 'joined',
                'rejoined' => 'rejoined (after declining)',
                'left' => 'left',
                'declined' => 'declined the call',
            ];
        @endphp
        @if($events->isNotEmpty())
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-3 border-b border-white/5">
                    <h2 class="text-sm font-semibold text-white">Timeline</h2>
                </div>
                <div class="divide-y divide-white/5">
                    @foreach($events as $event)
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <p class="text-sm text-slate-300 truncate">
                                <span class="font-semibold text-white">{{ $event['name'] }}</span>
                                {{ $eventLabels[$event['type']] ?? $event['type'] }}
                            </p>
                            <span class="text-xs text-slate-500 shrink-0">
                                {{ $event['at'] ? \Illuminate\Support\Carbon::parse($event['at'])->format('g:i:s A') : '' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex justify-center">
            <a href="{{ $callLog->chatUrl() }}"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
                Open {{ $callLog->chat_label }}
            </a>
        </div>
    </div>
@endsection
