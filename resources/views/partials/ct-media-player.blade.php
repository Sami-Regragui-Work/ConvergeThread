{{--
  Shared A/V player controls.
  Wrap with: <div x-data="ctMediaPlayer({ src, kind, compact, editable, previewIndex })">
  editable = composer staging: speed + trim are baked into the file on Apply / Send.
--}}
<div x-show="kind === 'video'" x-cloak
    class="relative rounded-xl overflow-hidden bg-black mb-1.5 w-full flex items-center justify-center"
    :class="compact ? 'flex-1 min-h-0' : 'aspect-video'"
    :style="compact ? 'min-height: min(62dvh, calc(100dvh - 14rem)); flex: 1 1 auto' : null">
    <video x-ref="videoEl" playsinline preload="metadata"
        class="max-h-full max-w-full w-full h-full object-contain bg-black"
        @timeupdate="onTime()"
        @loadedmetadata="onMeta()"
        @durationchange="onMeta()"
        @ended="onEnded()"
        @play="playing = true"
        @pause="playing = false"></video>
</div>
<audio x-ref="audioEl" preload="auto" class="hidden"
    @timeupdate="onTime()"
    @loadedmetadata="onMeta()"
    @durationchange="onMeta()"
    @ended="onEnded()"
    @play="playing = true"
    @pause="playing = false"></audio>

<div class="flex flex-col gap-1.5 shrink-0">
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" @click="togglePlay()"
            class="shrink-0 w-8 h-8 rounded-full bg-brand-500/90 hover:bg-brand-500 text-white flex items-center justify-center transition"
            :title="playing ? 'Pause' : 'Play'">
            <svg x-show="!playing" class="w-3.5 h-3.5 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <svg x-show="playing" x-cloak class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
        </button>
        <button type="button" @click="replay()"
            class="shrink-0 w-7 h-7 rounded-full border border-white/10 text-slate-300 hover:bg-white/5 flex items-center justify-center"
            title="Replay from start">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 13a7 7 0 0112.2-4.2L20 10M4 14l2.8 1.2A7 7 0 0019 11"/>
            </svg>
        </button>
        <input type="range" min="0" :max="Math.max(duration, 0.01)" step="0.01"
            :value="current"
            @input="seek($event.target.value)"
            class="flex-1 min-w-24 h-1.5 accent-brand-500 cursor-pointer">
        <span class="shrink-0 text-[10px] tabular-nums text-slate-400 min-w-16 text-right"
            x-text="formatTime(current) + ' / ' + formatTime(duration)"></span>
        <button type="button" @click="showSpeed = !showSpeed; if (showSpeed) showTrim = false; ensureControlsVisible()"
            class="shrink-0 min-w-10 px-1.5 py-1 rounded-lg border text-[10px] font-semibold transition"
            :class="showSpeed || Math.abs(rate - 1) > 0.001 ? 'border-brand-500/50 bg-brand-500/15 text-brand-300' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            title="Playback speed (baked into file when applied)"
            x-text="rateLabel()"></button>
        <button type="button" x-show="editable" x-cloak
            @click="showTrim = !showTrim; if (showTrim) showSpeed = false; ensureControlsVisible()"
            class="shrink-0 px-1.5 py-1 rounded-lg border text-[10px] font-semibold transition"
            :class="showTrim || isDirty() && (trimStart > 0.02 || (duration && effectiveTrimEnd() < duration - 0.05)) ? 'border-amber-500/50 bg-amber-500/10 text-amber-200' : 'border-white/10 text-slate-300 hover:bg-white/5'"
            title="Trim start / end">Trim</button>
    </div>

    <div x-show="showSpeed" x-cloak x-transition class="flex items-center gap-2 pl-2">
        <span class="text-[10px] text-slate-500 shrink-0">0.01×</span>
        <input type="range" min="0.01" max="3" step="0.01"
            :value="rate"
            @input="setRate($event.target.value)"
            class="flex-1 h-1.5 accent-emerald-500 cursor-pointer">
        <span class="text-[10px] text-slate-500 shrink-0">3×</span>
        <button type="button" @click="setRate(1)" class="text-[10px] text-brand-400 hover:text-brand-300 px-1 shrink-0">1×</button>
    </div>

    <div x-show="editable && showTrim" x-cloak x-transition class="space-y-1.5 pl-1">
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-slate-500 w-8 shrink-0">Start</span>
            <input type="range" min="0" :max="Math.max(duration, 0.01)" step="0.01"
                :value="trimStart"
                @input="setTrimStart($event.target.value)"
                class="flex-1 h-1.5 accent-amber-500 cursor-pointer">
            <span class="text-[10px] tabular-nums text-slate-400 w-10 text-right" x-text="formatTime(trimStart)"></span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] text-slate-500 w-8 shrink-0">End</span>
            <input type="range" min="0" :max="Math.max(duration, 0.01)" step="0.01"
                :value="effectiveTrimEnd()"
                @input="setTrimEnd($event.target.value)"
                class="flex-1 h-1.5 accent-amber-500 cursor-pointer">
            <span class="text-[10px] tabular-nums text-slate-400 w-10 text-right" x-text="formatTime(effectiveTrimEnd())"></span>
        </div>
        <p class="text-[10px] text-slate-500" x-text="'Selection ' + trimLabel()"></p>
    </div>

    <div x-show="editable" x-cloak data-ct-media-footer class="flex items-center justify-between gap-2 pt-0.5">
        <p class="text-[10px] text-slate-500 truncate"
            x-text="isDirty() ? 'Speed/trim will be written into the file on Apply or Send' : 'Original file'"></p>
        <button type="button" @click="applyEdit()"
            :disabled="!isDirty() || applying"
            class="shrink-0 px-2.5 py-1 rounded-lg text-[10px] font-semibold transition disabled:opacity-40"
            :class="isDirty() ? 'bg-brand-500 text-white hover:bg-brand-600' : 'border border-white/10 text-slate-400'">
            <span x-text="applying ? 'Applying…' : 'Apply'"></span>
        </button>
    </div>
    <p x-show="applyError" x-cloak class="text-[10px] text-red-400" x-text="applyError"></p>
</div>
