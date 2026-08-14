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
    <button type="button" x-show="canMeet && participants.length >= 3" @click="openCall('meet')" title="Start a meeting"
        class="p-2 rounded-lg border border-white/10 text-emerald-400 hover:bg-emerald-500/10 hover:text-emerald-300 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 3h16a1 1 0 011 1v11a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20h6M12 16v4"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 12.5l2.5-3 2 2 3-3.5"/>
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

<div x-show="showCallModal && callState !== 'idle' && callType !== 'meet'" x-cloak
    :class="callMinimized ? 'invisible pointer-events-none' : ''"
    class="fixed inset-0 z-200 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/70"></div>
    <div class="relative bg-surface-200 border border-white/10 rounded-2xl max-w-5xl w-full h-[92dvh] flex flex-col overflow-hidden shadow-2xl">
        <div class="flex items-center justify-between gap-3 px-5 pt-5 shrink-0">
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
            <div class="flex items-center gap-2 shrink-0">
                <span class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-lg border border-white/10 text-slate-400"
                    x-text="callState"></span>
                <button type="button" @click="minimizeCall()" title="Minimize — keep the call running while you chat"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            </div>
        </div>
        <p x-show="callError" x-cloak class="text-sm text-amber-300 px-5 pt-3 shrink-0" x-text="callError"></p>
        <div class="flex-1 min-h-0 flex flex-col gap-4 p-5">
            {{-- Hero region: shared screens are dominant when present --}}
            <div x-show="screenTopTiles().length" x-cloak class="flex-1 min-h-0">
                <div class="h-full grid gap-3 content-center"
                    :style="screenTopGridStyle()">
                <template x-for="tile in screenTopTiles()" :key="'top-screen-' + tile.key">
                    <div class="contents">
                        <template x-if="tile.local">
                            <div
                                class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black h-full w-full min-h-0 flex items-center justify-center select-none"
                                :class="screenDragging('local') ? 'cursor-grabbing' : (screenZoomed('local') ? 'cursor-grab' : '')"
                                @wheel.prevent="onScreenWheel($event, 'local')"
                                @mousedown="startScreenPan($event, 'local')"
                                @mousemove.window="onScreenMove($event)"
                                @mouseup.window="onScreenUp($event)">
                                <div class="absolute inset-0 pointer-events-none" :style="screenTransform('local')">
                                    <video x-ref="localScreenVideo" autoplay muted playsinline
                                        class="h-full w-full object-contain bg-black pointer-events-none"></video>
                                </div>
                                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">You · screen</span>
                                <div class="absolute top-2 right-2 flex gap-1.5">
                                    <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                        class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                    <button type="button" @click="toggleScreenPin('local')"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                        :class="screenPinned('local') ? 'bg-brand-500/80' : ''"
                                        :title="screenPinned('local') ? 'Unpin this screen' : 'Pin this screen on top'">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="resetScreenZoom('local')" title="Reset zoom"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="toggleScreenFullscreen($event, 'local')" title="Fullscreen"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template x-else>
                            <div
                                class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black h-full w-full min-h-0 flex items-center justify-center select-none"
                                :class="screenDragging('peer:' + tile.peer.userId) ? 'cursor-grabbing' : (screenZoomed('peer:' + tile.peer.userId) ? 'cursor-grab' : '')"
                                @wheel.prevent="onScreenWheel($event, 'peer:' + tile.peer.userId)"
                                @mousedown="startScreenPan($event, 'peer:' + tile.peer.userId)"
                                @mousemove.window="onScreenMove($event)"
                                @mouseup.window="onScreenUp($event)">
                                <div class="absolute inset-0 pointer-events-none" :style="screenTransform('peer:' + tile.peer.userId)">
                                    <video :id="'remote-screen-' + tile.peer.userId" autoplay muted playsinline
                                        class="h-full w-full object-contain bg-black pointer-events-none"
                                        x-effect="const t = tile.peer; try { if (t?.screenVideoTrack?.attach) { t.screenVideoTrack.attach($el); } else if (t?.screenStream) { $el.srcObject = t.screenStream; } } catch (err) { if (t?.screenStream) $el.srcObject = t.screenStream; } $el.muted = true; $el.play?.().catch(() => {});"></video>
                                </div>
                                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white" x-text="tile.peer.name + ' · screen'"></span>
                                <div class="absolute top-2 right-2 flex gap-1.5">
                                    <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                        class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                    <button type="button" @click="toggleScreenPin('peer:' + tile.peer.userId)"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                        :class="screenPinned('peer:' + tile.peer.userId) ? 'bg-brand-500/80' : ''"
                                        :title="screenPinned('peer:' + tile.peer.userId) ? 'Unpin this screen' : 'Pin this screen on top'">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="resetScreenZoom('peer:' + tile.peer.userId)" title="Reset zoom"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="toggleScreenFullscreen($event, 'peer:' + tile.peer.userId)" title="Fullscreen"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            </div>

            <div class="min-h-0 grid gap-3 content-start overflow-y-auto"
                :class="screenTopTiles().length ? 'shrink-0 h-48 grid-cols-2 md:grid-cols-3 xl:grid-cols-4 auto-rows-fr' : 'flex-1 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'">
                <div class="relative rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                    <video x-ref="localVideo" x-show="localShowsVideo() && !localVideoOff" autoplay muted playsinline
                        class="absolute inset-0 h-full w-full object-contain bg-black -scale-x-100"></video>
                    <div x-show="!localShowsVideo() || localVideoOff" class="relative z-10 text-center p-4">
                        <div class="w-14 h-14 mx-auto rounded-full bg-brand-500/20 text-brand-300 flex items-center justify-center text-lg font-bold"
                            x-text="(currentUserName || 'Y').slice(0, 1).toUpperCase()"></div>
                        <p class="text-xs text-slate-400 mt-2">You <span x-show="localMuted">(muted)</span></p>
                    </div>
                    <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">You</span>
                </div>
                <template x-for="peer in peers" :key="peer.userId">
                    <div class="contents">
                        <div class="relative group rounded-xl overflow-hidden border border-white/10 bg-black min-h-40 flex items-center justify-center">
                            <video x-show="peerShowsVideo(peer)" :id="'remote-video-' + peer.userId" autoplay playsinline
                                class="absolute inset-0 h-full w-full object-contain bg-black -scale-x-100"
                                x-effect="if ($el && peer.stream) { $el.srcObject = peer.stream; $el.muted = localDeafened || !!deafenPeerIds[peer.userId]; $el.play?.().catch(() => {}); }"></video>
                            {{-- Keep audio in DOM (not display:none) or browsers mute it --}}
                            <audio :id="'remote-audio-' + peer.userId" autoplay playsinline class="sr-only"
                                x-effect="if ($el && peer.stream) { $el.srcObject = peer.stream; $el.muted = peerShowsVideo(peer) || localDeafened || !!deafenPeerIds[peer.userId]; if (!peerShowsVideo(peer)) $el.play?.().catch(() => {}); }"></audio>
                            <div x-show="!peerShowsVideo(peer) || !peer.stream" class="relative z-10 text-center p-4">
                                <div class="w-14 h-14 mx-auto rounded-full bg-brand-500/20 text-brand-300 flex items-center justify-center text-lg font-bold"
                                    x-text="(peer.name || '?').slice(0, 1).toUpperCase()"></div>
                                <p class="text-xs text-slate-400 mt-2" x-text="peer.name"></p>
                            </div>
                            <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white" x-text="peer.name"></span>
                            <div class="absolute top-2 right-2 hidden group-hover:flex gap-1.5">
                                <button type="button" @click="toggleDeafenPeer(peer.userId)"
                                    class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                    :class="deafenPeerIds[peer.userId] ? 'bg-amber-500/70' : ''"
                                    :title="(deafenPeerIds[peer.userId] ? 'Unmute ' : 'Mute ') + (peer.name || 'this person') + ' for me'">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 8.5a6.5 6.5 0 1113 0c0 6-6 6-6 10a3.5 3.5 0 11-7 0"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 8.5a2.5 2.5 0 00-5 0v1a2 2 0 101 0"/>
                                        <path x-show="deafenPeerIds[peer.userId]" stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                {{-- Non-pinned screens go below like the other tiles --}}
                <template x-for="tile in screenBottomTiles()" :key="'bottom-screen-' + tile.key">
                    <div class="contents">
                        <template x-if="tile.local">
                            <div
                                class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black aspect-video min-h-40 flex items-center justify-center select-none"
                                :class="screenDragging('local') ? 'cursor-grabbing' : (screenZoomed('local') ? 'cursor-grab' : '')"
                                @wheel.prevent="onScreenWheel($event, 'local')"
                                @mousedown="startScreenPan($event, 'local')"
                                @mousemove.window="onScreenMove($event)"
                                @mouseup.window="onScreenUp($event)">
                                <div class="absolute inset-0 pointer-events-none" :style="screenTransform('local')">
                                    <video x-ref="localScreenVideo" autoplay muted playsinline
                                        class="h-full w-full object-contain bg-black pointer-events-none"></video>
                                </div>
                                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">You · screen</span>
                                <div class="absolute top-2 right-2 flex gap-1.5">
                                    <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                        class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                    <button type="button" @click="toggleScreenPin('local')"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                        :class="screenPinned('local') ? 'bg-brand-500/80' : ''"
                                        :title="screenPinned('local') ? 'Unpin this screen' : 'Pin this screen on top'">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="resetScreenZoom('local')" title="Reset zoom"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="toggleScreenFullscreen($event, 'local')" title="Fullscreen"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template x-else>
                            <div
                                class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black aspect-video min-h-40 flex items-center justify-center select-none"
                                :class="screenDragging('peer:' + tile.peer.userId) ? 'cursor-grabbing' : (screenZoomed('peer:' + tile.peer.userId) ? 'cursor-grab' : '')"
                                @wheel.prevent="onScreenWheel($event, 'peer:' + tile.peer.userId)"
                                @mousedown="startScreenPan($event, 'peer:' + tile.peer.userId)"
                                @mousemove.window="onScreenMove($event)"
                                @mouseup.window="onScreenUp($event)">
                                <div class="absolute inset-0 pointer-events-none" :style="screenTransform('peer:' + tile.peer.userId)">
                                    <video :id="'remote-screen-' + tile.peer.userId" autoplay muted playsinline
                                        class="h-full w-full object-contain bg-black pointer-events-none"
                                        x-effect="const t = tile.peer; try { if (t?.screenVideoTrack?.attach) { t.screenVideoTrack.attach($el); } else if (t?.screenStream) { $el.srcObject = t.screenStream; } } catch (err) { if (t?.screenStream) $el.srcObject = t.screenStream; } $el.muted = true; $el.play?.().catch(() => {});"></video>
                                </div>
                                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white" x-text="tile.peer.name + ' · screen'"></span>
                                <div class="absolute top-2 right-2 flex gap-1.5">
                                    <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                        class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                    <button type="button" @click="toggleScreenPin('peer:' + tile.peer.userId)"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                        :class="screenPinned('peer:' + tile.peer.userId) ? 'bg-brand-500/80' : ''"
                                        :title="screenPinned('peer:' + tile.peer.userId) ? 'Unpin this screen' : 'Pin this screen on top'">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="resetScreenZoom('peer:' + tile.peer.userId)" title="Reset zoom"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="toggleScreenFullscreen($event, 'peer:' + tile.peer.userId)" title="Fullscreen"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <div x-show="callState === 'outgoing' && peers.length === 0" class="rounded-xl border border-dashed border-white/10 min-h-40 flex items-center justify-center text-sm text-slate-500">
                    Waiting for someone to join…
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-2 px-5 pb-5 pt-1 shrink-0">
            <button type="button" @click="toggleMute()"
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl border transition"
                :class="localMuted ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
                :title="localMuted ? 'Unmute your microphone' : 'Mute your microphone'">
                <svg x-show="!localMuted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 14.5a3.5 3.5 0 003.5-3.5V7a3.5 3.5 0 10-7 0v4a3.5 3.5 0 003.5 3.5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.5 11a6.5 6.5 0 01-13 0M12 18v3"/>
                </svg>
                <svg x-show="localMuted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 14.5a3.5 3.5 0 003.5-3.5V7a3.5 3.5 0 10-7 0v4a3.5 3.5 0 003.5 3.5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.5 11a6.5 6.5 0 01-.75 3M5.5 11a6.5 6.5 0 006.5 6M12 18v3M4 4l16 16"/>
                </svg>
            </button>
            <button type="button" @click="toggleDeafen()"
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl border transition"
                :class="localDeafened ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
                :title="localDeafened ? 'Undeafen (hear others)' : 'Deafen (mute others for you)'">
                <svg x-show="!localDeafened" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                </svg>
                <svg x-show="localDeafened" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                    <path stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
                </svg>
            </button>
            <button type="button" x-show="callType === 'video'" @click="toggleVideo()"
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl border transition"
                :class="localVideoOff ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
                :disabled="sharingScreen"
                :title="localVideoOff ? 'Turn camera on' : 'Turn camera off'">
                <svg x-show="!localVideoOff" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h10a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z"/>
                </svg>
                <svg x-show="localVideoOff" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h10a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1zM3 3l18 18"/>
                </svg>
            </button>
            <button type="button" @click="toggleScreenShare()"
                class="inline-flex items-center justify-center w-10 h-10 rounded-xl border transition"
                :class="sharingScreen ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
                title="Share your screen">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </button>
            <button type="button" @click="endCall()"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-semibold transition">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08c-.18-.17-.29-.42-.29-.7 0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.7l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.1-.7-.28-.79-.73-1.68-1.36-2.66-1.85-.33-.16-.56-.51-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
                </svg>
                End call
            </button>
        </div>
    </div>
</div>

{{-- Meet: fullscreen group room (video for everyone, host controls) --}}
<div x-show="showCallModal && callState !== 'idle' && callType === 'meet'" x-cloak
    :class="callMinimized ? 'invisible pointer-events-none' : ''"
    class="fixed inset-0 z-200 flex flex-col bg-[#0b0f14]">
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/10 shrink-0">
        <div class="min-w-0">
            <p class="text-white font-semibold truncate">Meeting · {{ $chatLabel }}</p>
            <p class="text-xs text-slate-500">
                <span x-text="callState"></span>
                <span x-show="callMediaMode === 'sfu'" x-cloak>· SFU</span>
                <span x-show="callMediaMode === 'mesh'" x-cloak>· mesh</span>
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <span x-show="isCallHost()" x-cloak
                class="text-[10px] uppercase tracking-wide px-2 py-1 rounded-lg border border-brand-500/30 bg-brand-500/10 text-brand-300">Host</span>
            <span x-show="callMediaMode === 'sfu' && callMediaE2ee" x-cloak class="text-[10px] text-emerald-400">E2EE</span>
            <span x-show="callMediaMode === 'sfu' && !callMediaE2ee" x-cloak class="text-[10px] text-amber-400">media not E2EE</span>
            <button type="button" @click="minimizeCall()" title="Minimize — keep the meeting running while you chat"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>
    </div>

    <p x-show="meetNotice" x-cloak class="text-sm text-amber-300 text-center px-4 pt-3" x-text="meetNotice"></p>
    <p x-show="callError" x-cloak class="text-sm text-amber-300 text-center px-4 pt-3" x-text="callError"></p>

    <div class="flex-1 min-h-0 flex flex-col gap-4 p-4">
        {{-- Hero region: shared screens are dominant when present --}}
        <div x-show="screenTopTiles().length" x-cloak class="flex-1 min-h-0">
            <div class="h-full grid gap-3 content-center"
                :style="screenTopGridStyle()">
                <template x-for="tile in screenTopTiles()" :key="'top-screen-' + tile.key">
                    <div class="contents">
                        <template x-if="tile.local">
                            <div
                                class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black h-full w-full min-h-0 select-none"
                                :class="screenDragging('local') ? 'cursor-grabbing' : (screenZoomed('local') ? 'cursor-grab' : '')"
                                @wheel.prevent="onScreenWheel($event, 'local')"
                                @mousedown="startScreenPan($event, 'local')"
                                @mousemove.window="onScreenMove($event)"
                                @mouseup.window="onScreenUp($event)">
                                <div class="absolute inset-0 pointer-events-none" :style="screenTransform('local')">
                                    <video x-ref="meetLocalScreenVideo" autoplay muted playsinline
                                        class="h-full w-full object-contain bg-black pointer-events-none"></video>
                                </div>
                                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">Your screen</span>
                                <div class="absolute top-2 right-2 flex gap-1.5">
                                    <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                        class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                    <button type="button" @click="toggleScreenPin('local')"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                        :class="screenPinned('local') ? 'bg-brand-500/80' : ''"
                                        :title="screenPinned('local') ? 'Unpin this screen' : 'Pin this screen on top'">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="resetScreenZoom('local')" title="Reset zoom"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="toggleScreenFullscreen($event, 'local')" title="Fullscreen"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template x-else>
                            <div
                                class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black h-full w-full min-h-0 select-none"
                                :class="screenDragging('peer:' + tile.peer.userId) ? 'cursor-grabbing' : (screenZoomed('peer:' + tile.peer.userId) ? 'cursor-grab' : '')"
                                @wheel.prevent="onScreenWheel($event, 'peer:' + tile.peer.userId)"
                                @mousedown="startScreenPan($event, 'peer:' + tile.peer.userId)"
                                @mousemove.window="onScreenMove($event)"
                                @mouseup.window="onScreenUp($event)">
                                <div class="absolute inset-0 pointer-events-none" :style="screenTransform('peer:' + tile.peer.userId)">
                                    <video :id="'meet-remote-screen-' + tile.peer.userId" autoplay muted playsinline
                                        class="h-full w-full object-contain bg-black pointer-events-none"
                                        x-effect="const t = tile.peer; try { if (t?.screenVideoTrack?.attach) { t.screenVideoTrack.attach($el); } else if (t?.screenStream) { $el.srcObject = t.screenStream; } } catch (err) { if (t?.screenStream) $el.srcObject = t.screenStream; } $el.muted = true; $el.play?.().catch(() => {});"></video>
                                </div>
                                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white"
                                    x-text="tile.peer.name + ' · screen'"></span>
                                <div class="absolute top-2 right-2 flex gap-1.5">
                                    <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                        class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                    <button type="button" @click="toggleScreenPin('peer:' + tile.peer.userId)"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                        :class="screenPinned('peer:' + tile.peer.userId) ? 'bg-brand-500/80' : ''"
                                        :title="screenPinned('peer:' + tile.peer.userId) ? 'Unpin this screen' : 'Pin this screen on top'">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="resetScreenZoom('peer:' + tile.peer.userId)" title="Reset zoom"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                        </svg>
                                    </button>
                                    <button type="button" @click="toggleScreenFullscreen($event, 'peer:' + tile.peer.userId)" title="Fullscreen"
                                        class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            </div>

            <div class="min-h-0 grid gap-3 content-start overflow-y-auto"
                :class="screenTopTiles().length ? 'shrink-0 h-48 grid-cols-2 md:grid-cols-3 xl:grid-cols-4 auto-rows-fr' : 'flex-1 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'">

            {{-- Local camera: its own tile, stays visible while sharing --}}
            <div class="relative rounded-xl overflow-hidden border border-white/10 bg-black aspect-video min-h-40">
                <video x-ref="meetLocalVideo" autoplay muted playsinline
                    class="absolute inset-0 h-full w-full object-cover bg-black -scale-x-100"
                    x-show="!localVideoOff"></video>
                <div x-show="localVideoOff" class="absolute inset-0 flex flex-col items-center justify-center gap-2">
                    <div class="w-14 h-14 rounded-full bg-brand-500/20 text-brand-300 flex items-center justify-center text-lg font-bold"
                        x-text="(currentUserName || 'Y').slice(0, 1).toUpperCase()"></div>
                </div>
                <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">
                    You <span x-show="localMuted">· muted</span>
                </span>
            </div>

            <template x-for="uid in meetPeerIds()" :key="'meet-participant-' + uid">
                <div class="contents">
                    {{-- Remote camera: its own tile, stays visible while they share --}}
                    <div class="relative group rounded-xl overflow-hidden border border-white/10 bg-black aspect-video min-h-40">
                        <video x-show="meetPeer(uid)?.stream" :id="'meet-remote-' + uid" autoplay playsinline
                            class="absolute inset-0 h-full w-full object-cover bg-black -scale-x-100"
                            x-effect="if ($el && meetPeer(uid)?.stream) { $el.srcObject = meetPeer(uid).stream; $el.muted = localDeafened || !!hostMutedIds[uid] || !!deafenPeerIds[uid]; $el.play?.().catch(() => {}); }"></video>
                        <div x-show="!meetPeer(uid)?.stream" class="absolute inset-0 flex flex-col items-center justify-center gap-2">
                            <div class="w-14 h-14 rounded-full bg-surface-300/40 text-slate-300 flex items-center justify-center text-lg font-bold"
                                x-text="(meetName(uid) || '?').slice(0, 1).toUpperCase()"></div>
                            <p class="text-xs text-slate-400" x-text="meetName(uid)"></p>
                        </div>
                        <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white"
                            x-text="meetName(uid)"></span>
                        <div class="absolute top-2 right-2 hidden group-hover:flex gap-1.5">
                            <button type="button" @click="toggleDeafenPeer(uid)"
                                class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                :class="deafenPeerIds[uid] ? 'bg-amber-500/70' : ''"
                                :title="(deafenPeerIds[uid] ? 'Unmute ' : 'Mute ') + meetName(uid) + ' for me'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 8.5a6.5 6.5 0 1113 0c0 6-6 6-6 10a3.5 3.5 0 11-7 0"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 8.5a2.5 2.5 0 00-5 0v1a2 2 0 101 0"/>
                                    <path x-show="deafenPeerIds[uid]" stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
                                </svg>
                            </button>
                            <button type="button" @click="muteForEveryone(uid)" x-show="isCallHost()" x-cloak
                                class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                :class="hostMutedIds[uid] ? 'bg-amber-500/70' : ''"
                                :title="(hostMutedIds[uid] ? 'Unmute ' : 'Mute ') + meetName(uid) + ' for everyone'">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 14.5a3.5 3.5 0 003.5-3.5V7a3.5 3.5 0 10-7 0v4a3.5 3.5 0 003.5 3.5zM18.5 11a6.5 6.5 0 01-.75 3M12 18v3M4 4l16 16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Non-pinned screens go below like the other tiles --}}
            <template x-for="tile in screenBottomTiles()" :key="'bottom-screen-' + tile.key">
                <div class="contents">
                    <template x-if="tile.local">
                        <div
                            class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black aspect-video min-h-40 select-none"
                            :class="screenDragging('local') ? 'cursor-grabbing' : (screenZoomed('local') ? 'cursor-grab' : '')"
                            @wheel.prevent="onScreenWheel($event, 'local')"
                            @mousedown="startScreenPan($event, 'local')"
                            @mousemove.window="onScreenMove($event)"
                            @mouseup.window="onScreenUp($event)">
                            <div class="absolute inset-0 pointer-events-none" :style="screenTransform('local')">
                                <video x-ref="meetLocalScreenVideo" autoplay muted playsinline
                                    class="h-full w-full object-contain bg-black pointer-events-none"></video>
                            </div>
                            <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">Your screen</span>
                            <div class="absolute top-2 right-2 flex gap-1.5">
                                <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                    class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                <button type="button" @click="toggleScreenPin('local')"
                                    class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                    :class="screenPinned('local') ? 'bg-brand-500/80' : ''"
                                    :title="screenPinned('local') ? 'Unpin this screen' : 'Pin this screen on top'">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                    </svg>
                                </button>
                                <button type="button" @click="resetScreenZoom('local')" title="Reset zoom"
                                    class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                    </svg>
                                </button>
                                <button type="button" @click="toggleScreenFullscreen($event, 'local')" title="Fullscreen"
                                    class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                    <template x-else>
                        <div
                            class="ct-screen-tile relative rounded-xl overflow-hidden border border-white/10 bg-black aspect-video min-h-40 select-none"
                            :class="screenDragging('peer:' + tile.peer.userId) ? 'cursor-grabbing' : (screenZoomed('peer:' + tile.peer.userId) ? 'cursor-grab' : '')"
                            @wheel.prevent="onScreenWheel($event, 'peer:' + tile.peer.userId)"
                            @mousedown="startScreenPan($event, 'peer:' + tile.peer.userId)"
                            @mousemove.window="onScreenMove($event)"
                            @mouseup.window="onScreenUp($event)">
                            <div class="absolute inset-0 pointer-events-none" :style="screenTransform('peer:' + tile.peer.userId)">
                                <video :id="'meet-remote-screen-' + tile.peer.userId" autoplay muted playsinline
                                    class="h-full w-full object-contain bg-black pointer-events-none"
                                    x-effect="const t = tile.peer; try { if (t?.screenVideoTrack?.attach) { t.screenVideoTrack.attach($el); } else if (t?.screenStream) { $el.srcObject = t.screenStream; } } catch (err) { if (t?.screenStream) $el.srcObject = t.screenStream; } $el.muted = true; $el.play?.().catch(() => {});"></video>
                            </div>
                            <span class="absolute bottom-2 left-2 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white"
                                x-text="tile.peer.name + ' · screen'"></span>
                            <div class="absolute top-2 right-2 flex gap-1.5">
                                <button type="button" title="Move your mouse inside the screen and scroll to zoom in/out. Drag to pan."
                                    class="w-7 h-7 rounded-md bg-black/60 text-white text-xs font-bold hover:bg-black/80">?</button>
                                <button type="button" @click="toggleScreenPin('peer:' + tile.peer.userId)"
                                    class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center transition"
                                    :class="screenPinned('peer:' + tile.peer.userId) ? 'bg-brand-500/80' : ''"
                                    :title="screenPinned('peer:' + tile.peer.userId) ? 'Unpin this screen' : 'Pin this screen on top'">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M16 9V4h1c.55 0 1-.45 1-1s-.45-1-1-1H7c-.55 0-1 .45-1 1s.45 1 1 1h1v5c0 1.66-1.34 3-3 3v2h5.97v7l1 1 1-1v-7H19v-2c-1.66 0-3-1.34-3-3z"/>
                                    </svg>
                                </button>
                                <button type="button" @click="resetScreenZoom('peer:' + tile.peer.userId)" title="Reset zoom"
                                    class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                                    </svg>
                                </button>
                                <button type="button" @click="toggleScreenFullscreen($event, 'peer:' + tile.peer.userId)" title="Fullscreen"
                                    class="w-7 h-7 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            </div>
        </div>

    <div class="flex flex-wrap items-center justify-center gap-3 py-4 shrink-0">
        <button type="button" @click="toggleMute()"
            class="inline-flex items-center justify-center w-11 h-11 rounded-xl border transition"
            :class="localMuted ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            :title="localMuted ? 'Unmute your microphone' : 'Mute your microphone'">
            <svg x-show="!localMuted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 14.5a3.5 3.5 0 003.5-3.5V7a3.5 3.5 0 10-7 0v4a3.5 3.5 0 003.5 3.5z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M18.5 11a6.5 6.5 0 01-13 0M12 18v3"/>
            </svg>
            <svg x-show="localMuted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 14.5a3.5 3.5 0 003.5-3.5V7a3.5 3.5 0 10-7 0v4a3.5 3.5 0 003.5 3.5z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M18.5 11a6.5 6.5 0 01-.75 3M5.5 11a6.5 6.5 0 006.5 6M12 18v3M4 4l16 16"/>
            </svg>
        </button>
        <button type="button" @click="toggleDeafen()"
            class="inline-flex items-center justify-center w-11 h-11 rounded-xl border transition"
            :class="localDeafened ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            :title="localDeafened ? 'Undeafen (hear others)' : 'Deafen (mute others for you)'">
            <svg x-show="!localDeafened" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
            </svg>
            <svg x-show="localDeafened" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                <path stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
            </svg>
        </button>
        <button type="button" @click="toggleVideo()"
            class="inline-flex items-center justify-center w-11 h-11 rounded-xl border transition"
            :class="localVideoOff ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            :disabled="sharingScreen"
            :title="localVideoOff ? 'Turn camera on' : 'Turn camera off'">
            <svg x-show="!localVideoOff" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h10a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z"/>
            </svg>
            <svg x-show="localVideoOff" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h10a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1zM3 3l18 18"/>
            </svg>
        </button>
        <button type="button" @click="toggleScreenShare()"
            class="inline-flex items-center justify-center w-11 h-11 rounded-xl border transition"
            :class="sharingScreen ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            title="Share your screen">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </button>
        <button type="button" @click="isCallHost() ? endMeeting() : endCall()"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-sm font-semibold transition">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08c-.18-.17-.29-.42-.29-.7 0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.7l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.1-.7-.28-.79-.73-1.68-1.36-2.66-1.85-.33-.16-.56-.51-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
            </svg>
            <span x-text="isCallHost() ? 'End meeting' : 'Leave'"></span>
        </button>
    </div>
</div>

{{-- Minimized in-call bar: keeps the call usable while you chat --}}
<div x-show="showCallModal && callState !== 'idle' && callMinimized" x-cloak
    class="fixed inset-x-0 bottom-24 z-[180] flex justify-center px-4 pointer-events-none">
    <div class="pointer-events-auto flex items-center gap-1.5 max-w-full overflow-x-auto rounded-2xl border border-white/10 bg-surface-200/95 shadow-2xl backdrop-blur px-3 py-2">
        <div class="flex items-center gap-2 pr-2 shrink-0">
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
            </span>
            <div class="min-w-0">
                <p class="text-xs text-white font-semibold leading-tight truncate"
                    x-text="callType === 'meet' ? 'In meeting' : (callType === 'video' ? 'In video call' : 'In voice call')"></p>
                <p class="text-[10px] text-slate-400 leading-tight truncate"
                    x-text="callLabel()"></p>
            </div>
        </div>
        <div class="h-6 w-px bg-white/10 mx-1 shrink-0"></div>
        <button type="button" @click="restoreCall()" title="Restore call"
            class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl border border-white/10 text-slate-300 hover:bg-white/5 hover:text-white transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
            </svg>
        </button>
        <button type="button" @click="toggleMute()"
            class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl border transition"
            :class="localMuted ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            :title="localMuted ? 'Unmute your microphone' : 'Mute your microphone'">
            <svg x-show="!localMuted" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 14.5a3.5 3.5 0 003.5-3.5V7a3.5 3.5 0 10-7 0v4a3.5 3.5 0 003.5 3.5zM18.5 11a6.5 6.5 0 01-13 0M12 18v3"/>
            </svg>
            <svg x-show="localMuted" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 14.5a3.5 3.5 0 003.5-3.5V7a3.5 3.5 0 10-7 0v4a3.5 3.5 0 003.5 3.5zM18.5 11a6.5 6.5 0 01-.75 3M5.5 11a6.5 6.5 0 006.5 6M12 18v3M4 4l16 16"/>
            </svg>
        </button>
        <button type="button" @click="toggleDeafen()"
            class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl border transition"
            :class="localDeafened ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            :title="localDeafened ? 'Undeafen (hear others)' : 'Deafen (mute others for you)'">
            <svg x-show="!localDeafened" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
            </svg>
            <svg x-show="localDeafened" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M6 8.5a6.5 6.5 0 1 1 13 0c0 6-6 6-6 10a3.5 3.5 0 1 1-7 0M15 8.5a2.5 2.5 0 0 0-5 0v1a2 2 0 1 1 0 4"/>
                <path stroke-linecap="round" stroke-width="2" d="M3 3l18 18"/>
            </svg>
        </button>
        <button type="button" x-show="callType === 'video' || callType === 'meet'" @click="toggleVideo()"
            class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl border transition"
            :class="localVideoOff ? 'border-amber-500/40 bg-amber-500/10 text-amber-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            :disabled="sharingScreen"
            :title="localVideoOff ? 'Turn camera on' : 'Turn camera off'">
            <svg x-show="!localVideoOff" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h10a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z"/>
            </svg>
            <svg x-show="localVideoOff" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M4 6h10a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1zM3 3l18 18"/>
            </svg>
        </button>
        <button type="button" @click="toggleScreenShare()"
            class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl border transition"
            :class="sharingScreen ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            title="Share your screen">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </button>
        <button type="button" @click="isCallHost() ? endMeeting() : endCall()"
            class="shrink-0 inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500/30 text-xs font-semibold transition"
            :title="isCallHost() ? 'End meeting' : 'Leave call'">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 9c-1.6 0-3.15.25-4.6.72v3.1c0 .39-.23.74-.56.9-.98.49-1.87 1.12-2.66 1.85-.18.18-.43.28-.7.28-.28 0-.53-.11-.71-.29L.29 13.08c-.18-.17-.29-.42-.29-.7 0-.28.11-.53.29-.71C3.34 8.78 7.46 7 12 7s8.66 1.78 11.71 4.67c.18.18.29.43.29.71 0 .28-.11.53-.29.7l-2.48 2.48c-.18.18-.43.29-.71.29-.27 0-.52-.1-.7-.28-.79-.73-1.68-1.36-2.66-1.85-.33-.16-.56-.51-.56-.9v-3.1C15.15 9.25 13.6 9 12 9z"/>
            </svg>
            <span x-text="isCallHost() ? 'End meeting' : 'Leave'"></span>
        </button>
    </div>
</div>

{{-- Fullscreen screen viewer: teleported to <body> so it covers the whole viewport.
     (A `fixed` tile inside the modal only grew a little because the modal's flex/overflow
     clipped it, so the viewer lives outside the modal entirely.) --}}
<template x-teleport="body">
    <div x-show="screenMaxTile()" x-cloak
        class="fixed inset-0 z-[400] bg-black flex items-center justify-center select-none"
        :class="screenDragging(maximizedScreenKey) ? 'cursor-grabbing' : (screenZoomed(maximizedScreenKey) ? 'cursor-grab' : '')"
        @click.self="closeScreenMaximize()"
        @wheel.prevent="onScreenWheel($event, maximizedScreenKey)"
        @mousedown="startScreenPan($event, maximizedScreenKey)"
        @mousemove.window="onScreenMove($event)"
        @mouseup.window="onScreenUp($event)">
        <div class="absolute inset-0 pointer-events-none" :style="screenTransform(maximizedScreenKey)">
            <video autoplay muted playsinline
                class="h-full w-full object-contain bg-black"
                x-effect="const tile = screenMaxTile(); try { if (tile && !tile.local && tile.peer?.screenVideoTrack?.attach) { tile.peer.screenVideoTrack.attach($el); } else { $el.srcObject = tile ? (tile.local ? screenStream : (tile.peer?.screenStream || null)) : null; } } catch (err) { $el.srcObject = tile ? (tile.local ? screenStream : (tile.peer?.screenStream || null)) : null; } $el.muted = true; $el.play?.().catch(() => {});"></video>
        </div>
        <span class="absolute bottom-4 left-4 text-xs px-2 py-1 rounded bg-black/60 text-white"
            x-text="(screenMaxTile()?.local ? 'You' : (screenMaxTile()?.peer?.name || '')) + ' · screen'"></span>
        <div class="absolute top-4 right-4 flex gap-2">
            <button type="button" @click="resetScreenZoom(maximizedScreenKey)" title="Reset zoom"
                class="w-9 h-9 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 14a7 7 0 0 0 11.3 3M19 10a7 7 0 0 0-11.3-3"/>
                </svg>
            </button>
            <button type="button" @click="closeScreenMaximize()" title="Exit fullscreen"
                class="w-9 h-9 rounded-md bg-black/60 text-white hover:bg-black/80 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/>
                </svg>
            </button>
        </div>
    </div>
</template>
@endif
