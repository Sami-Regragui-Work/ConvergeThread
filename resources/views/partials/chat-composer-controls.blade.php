@php
    $composerPlaceholder = $composerPlaceholder ?? 'Message… paste or drop files';
    $composerSubmitLabel = $composerSubmitLabel ?? 'Send';
@endphp
<div class="relative" @click.outside="closeMentionMenu()">
    <div x-show="showMentionMenu" x-cloak
        class="absolute bottom-full left-0 right-0 mb-2 max-h-48 overflow-y-auto rounded-xl border border-white/10 bg-surface-300 shadow-xl z-40">
        <template x-for="(item, index) in filteredSuggestions()" :key="item.token">
            <button type="button"
                @mousedown.prevent="pickSuggestion(item)"
                class="w-full text-left px-4 py-2.5 text-sm flex items-center justify-between gap-4"
                :class="activeMentionIndex === index ? 'bg-white/10' : 'hover:bg-white/5'">
                <span class="font-medium shrink-0"
                    :style="item.color ? 'color:' + item.color : ''"
                    :class="!item.color ? 'text-brand-300' : ''"
                    x-text="item.token"></span>
                <span class="text-slate-500 truncate text-right" x-text="item.label"></span>
            </button>
        </template>
    </div>

    <div class="flex items-end gap-2">
        <button type="button" @click="toggleMentionMenu()"
            class="shrink-0 w-10 h-10 rounded-xl border border-white/10 bg-surface-200 text-brand-400 hover:bg-white/5 transition font-bold"
            title="Mention someone">&#64;</button>
        <input type="text" x-ref="draftInput" x-model="draft"
            @input="onDraftInput()"
            @keydown="onDraftKeydown($event)"
            @paste="onComposerPaste($event)"
            autocomplete="off"
            placeholder="{{ $composerPlaceholder }}"
            :disabled="sending"
            class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition placeholder-slate-500 disabled:opacity-50">
        <label class="shrink-0 w-10 h-10 flex items-center justify-center rounded-xl border border-white/10 bg-surface-200 text-slate-400 hover:bg-white/5 hover:text-white cursor-pointer transition"
            title="Attach files (max 50 MB each)">
            <input type="file" x-ref="fileInput" accept="*/*" multiple @change="onFilesChange($event)" class="sr-only">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
            </svg>
        </label>
        <button type="button" @click="toggleRecording()"
            class="shrink-0 w-10 h-10 flex items-center justify-center rounded-xl border transition"
            :class="recording ? 'border-red-500/50 bg-red-500/15 text-red-300' : 'border-white/10 bg-surface-200 text-slate-400 hover:bg-white/5 hover:text-white'"
            :title="recording ? ('Stop recording (' + recordSeconds + 's)') : 'Record voice note'">
            <svg x-show="!recording" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 14a3 3 0 003-3V7a3 3 0 10-6 0v4a3 3 0 003 3zm5-3a5 5 0 01-10 0H5a7 7 0 0014 0h-2zm-5 9a1 1 0 01-1-1v-2.07A7.002 7.002 0 015 11h2a5 5 0 0010 0h2a7.002 7.002 0 01-6 6.93V19a1 1 0 01-1 1z"/>
            </svg>
            <span x-show="recording" x-cloak class="w-3 h-3 rounded-full bg-red-400 animate-pulse"></span>
        </button>
        <button type="submit" :disabled="sending || (!draft.trim() && !files.length) || recording"
            class="shrink-0 bg-brand-500 hover:bg-brand-600 disabled:opacity-50 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition">
            <span x-text="sending ? '…' : @js($composerSubmitLabel)"></span>
        </button>
    </div>
</div>
<div x-show="filePreviews.length" x-cloak class="flex flex-wrap gap-2">
    <template x-for="(preview, index) in filePreviews" :key="preview.key || index">
        <div class="relative group/file rounded-xl border border-white/10 bg-surface-200 overflow-hidden w-20 h-20 shrink-0">
            <img x-show="preview.isImage" :src="preview.url" :alt="preview.name" class="h-full w-full object-cover min-w-16 min-h-16 max-w-20 max-h-20">
            <div x-show="preview.isVideo" class="relative h-full w-full bg-black">
                <video :src="preview.url" class="h-full w-full object-cover" muted playsinline></video>
                <span class="absolute inset-0 flex items-center justify-center text-white text-xs">▶</span>
            </div>
            <div x-show="preview.isAudio" class="h-full w-full flex flex-col items-center justify-center px-1.5 text-center gap-0.5 bg-violet-500/10">
                <span class="text-[9px] font-bold uppercase text-violet-300">AUD</span>
                <span class="text-[9px] text-slate-500" x-text="preview.sizeLabel"></span>
            </div>
            <div x-show="!preview.isImage && !preview.isVideo && !preview.isAudio" class="h-full w-full flex flex-col items-center justify-center px-1.5 text-center gap-0.5">
                <span class="text-[9px] font-bold uppercase text-slate-400" x-text="preview.ext"></span>
                <span class="text-[9px] text-slate-500 truncate w-full" x-text="preview.name"></span>
                <span class="text-[9px] text-slate-600" x-text="preview.sizeLabel"></span>
            </div>
            <button type="button" @click="removeFile(index)"
                class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white text-xs opacity-0 group-hover/file:opacity-100 transition">×</button>
        </div>
    </template>
</div>
<p x-show="recording" x-cloak class="text-xs text-red-300">Recording… <span x-text="recordSeconds + 's'"></span> — click mic to stop</p>
<p x-show="sendError" x-cloak class="text-xs text-red-400" x-text="sendError"></p>
