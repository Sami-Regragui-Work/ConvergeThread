{{-- Call UI pieces. Use $mode = buttons|modals|all --}}
@php
    $chatLabel = $chatLabel ?? 'chat';
    $mode = $mode ?? 'all';
@endphp

@if($mode === 'buttons' || $mode === 'all')
    <button type="button" @click="openCall('voice')" title="Voice call"
        class="p-2 rounded-lg border border-white/10 text-slate-400 hover:bg-white/5 hover:text-white transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
        </svg>
    </button>
    <button type="button" @click="openCall('video')" title="Video call"
        class="p-2 rounded-lg border border-white/10 text-slate-400 hover:bg-white/5 hover:text-white transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
    </button>
@endif

@if($mode === 'modals' || $mode === 'all')
<div x-show="incomingCall" x-cloak class="fixed inset-0 z-200 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60"></div>
    <div class="relative bg-surface-200 border border-white/10 rounded-2xl p-6 max-w-sm w-full shadow-2xl text-center space-y-4">
        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/10 text-emerald-300 flex items-center justify-center text-xl font-bold"
            x-text="(incomingCall?.from_user_name || '?').slice(0, 1).toUpperCase()"></div>
        <div>
            <p class="text-white font-semibold" x-text="(incomingCall?.from_user_name || 'Someone') + ' is calling'"></p>
            <p class="text-sm text-slate-500 mt-1"
                x-text="(incomingCall?.call_type === 'video' ? 'Video' : 'Voice') + ' call · {{ $chatLabel }}'"></p>
        </div>
        <div class="flex gap-3">
            <button type="button" @click="rejectIncoming()"
                class="flex-1 py-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-semibold transition">Decline</button>
            <button type="button" @click="acceptIncoming()"
                class="flex-1 py-2.5 rounded-xl bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 text-sm font-semibold transition">Accept</button>
        </div>
    </div>
</div>

<div x-show="showCallModal && callState !== 'idle'" x-cloak class="fixed inset-0 z-200 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/70"></div>
    <div class="relative bg-surface-200 border border-white/10 rounded-2xl p-5 sm:p-6 max-w-3xl w-full max-h-[90dvh] overflow-y-auto shadow-2xl space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-white font-semibold"
                    x-text="callState === 'outgoing' ? ('Calling…') : (sharingScreen ? 'Sharing screen' : (callType === 'video' ? 'Video call' : 'Voice call'))"></p>
                <p class="text-xs text-slate-500">
                    {{ $chatLabel }}
                    <span x-show="callMediaMode === 'sfu'" x-cloak>· SFU</span>
                    <span x-show="callMediaMode === 'mesh'" x-cloak>· mesh</span>
                    <span x-show="callMediaMode === 'sfu' && callMediaE2ee" x-cloak class="text-emerald-400">· E2EE</span>
                    <span x-show="callMediaMode === 'sfu' && !callMediaE2ee" x-cloak class="text-amber-400">· media not E2EE</span>
                </p>
            </div>
            <span class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-lg border border-white/10 text-slate-400"
                x-text="callState"></span>
        </div>
        <p x-show="callError" x-cloak class="text-sm text-amber-300" x-text="callError"></p>
        <div class="grid gap-3" :class="(localShowsVideo() || localShowsScreen() || peers.some(p => peerShowsVideo(p) || (p.screenSharing && p.screenStream))) ? 'sm:grid-cols-2' : ''">
            <div class="relative rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                <video x-ref="localVideo" x-show="localShowsVideo() && !localVideoOff" autoplay muted playsinline
                    class="absolute inset-0 h-full w-full object-contain bg-black"></video>
                <div x-show="!localShowsVideo() || localVideoOff" class="relative z-10 text-center p-4">
                    <div class="w-14 h-14 mx-auto rounded-full bg-brand-500/20 text-brand-300 flex items-center justify-center text-lg font-bold"
                        x-text="(currentUserName || 'Y').slice(0, 1).toUpperCase()"></div>
                    <p class="text-xs text-slate-400 mt-2">You <span x-show="localMuted">(muted)</span></p>
                </div>
                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">You</span>
            </div>
            <div x-show="localShowsScreen()" x-cloak
                class="relative rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                <video x-ref="localScreenVideo" autoplay muted playsinline
                    class="absolute inset-0 h-full w-full object-contain bg-black"></video>
                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">You · screen</span>
            </div>
            <template x-for="peer in peers" :key="peer.userId">
                <div class="contents">
                    <div class="relative rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                        <video x-show="peerShowsVideo(peer)" :id="'remote-video-' + peer.userId" autoplay playsinline
                            class="absolute inset-0 h-full w-full object-contain bg-black"
                            x-effect="if ($el && peer.stream) { $el.srcObject = peer.stream; $el.play?.().catch(() => {}); }"></video>
                        {{-- Keep audio in DOM (not display:none) or browsers mute it --}}
                        <audio :id="'remote-audio-' + peer.userId" autoplay playsinline class="sr-only"
                            x-effect="if ($el && peer.stream) { $el.srcObject = peer.stream; $el.muted = peerShowsVideo(peer); if (!peerShowsVideo(peer)) $el.play?.().catch(() => {}); }"></audio>
                        <div x-show="!peerShowsVideo(peer) || !peer.stream" class="relative z-10 text-center p-4">
                            <div class="w-14 h-14 mx-auto rounded-full bg-brand-500/20 text-brand-300 flex items-center justify-center text-lg font-bold"
                                x-text="(peer.name || '?').slice(0, 1).toUpperCase()"></div>
                            <p class="text-xs text-slate-400 mt-2" x-text="peer.name"></p>
                        </div>
                        <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white" x-text="peer.name"></span>
                    </div>
                    <div x-show="peer.screenSharing && peer.screenStream" x-cloak
                        class="relative rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                        <video :id="'remote-screen-' + peer.userId" autoplay playsinline
                            class="absolute inset-0 h-full w-full object-contain bg-black"
                            x-effect="if ($el && peer.screenStream) { $el.srcObject = peer.screenStream; $el.play?.().catch(() => {}); }"></video>
                        <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white" x-text="peer.name + ' · screen'"></span>
                    </div>
                </div>
            </template>
            <div x-show="callState === 'outgoing' && peers.length === 0" class="rounded-xl border border-dashed border-white/10 min-h-40 flex items-center justify-center text-sm text-slate-500">
                Waiting for someone to join…
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
            <button type="button" @click="toggleMute()"
                class="px-3 py-2 rounded-xl border text-sm transition"
                :class="localMuted ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'">
                <span x-text="localMuted ? 'Unmute' : 'Mute'"></span>
            </button>
            <button type="button" x-show="callType === 'video'" @click="toggleVideo()"
                class="px-3 py-2 rounded-xl border text-sm transition"
                :class="localVideoOff ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
                :disabled="sharingScreen">
                <span x-text="localVideoOff ? 'Camera on' : 'Camera off'"></span>
            </button>
            <button type="button" @click="toggleScreenShare()"
                class="px-3 py-2 rounded-xl border text-sm transition"
                :class="sharingScreen ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
                title="Share your screen">
                <span x-text="sharingScreen ? 'Stop sharing' : 'Share screen'"></span>
            </button>
            <button type="button" @click="endCall()"
                class="px-4 py-2 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-semibold transition">
                End call
            </button>
        </div>
    </div>
</div>
@endif
