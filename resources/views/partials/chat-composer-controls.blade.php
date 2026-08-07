@php
    $composerPlaceholder = $composerPlaceholder ?? 'Message… paste or drop files';
    $composerSubmitLabel = $composerSubmitLabel ?? 'Send';
@endphp

{{-- Staging: full-size layout only when a video is attached --}}
<div x-show="filePreviews.length" x-cloak
    class="overflow-y-auto overscroll-contain space-y-2 pb-3"
    :class="hasVideoStage() ? 'min-h-0 flex-1 pb-6' : 'shrink-0 max-h-56'"
    :style="hasVideoStage() ? 'flex: 1 1 0%; min-height: 0; scrollbar-gutter: stable' : null">
    <div x-show="filePreviews.some(p => !p.isAudio && !p.isVideo)" class="flex flex-wrap gap-2">
        <template x-for="(preview, index) in filePreviews" :key="'thumb-' + (preview.key || index)">
            <div x-show="!preview.isAudio && !preview.isVideo"
                class="relative group/file rounded-xl border border-white/10 bg-surface-200 overflow-hidden w-20 h-20 shrink-0">
                <img x-show="preview.isImage" :src="preview.url" :alt="preview.name"
                    class="h-full w-full object-cover min-w-16 min-h-16 max-w-20 max-h-20">
                <div x-show="!preview.isImage" class="h-full w-full flex flex-col items-center justify-center px-1.5 text-center gap-0.5">
                    <span class="text-[9px] font-bold uppercase text-slate-400" x-text="preview.ext"></span>
                    <span class="text-[9px] text-slate-500 truncate w-full" x-text="preview.name"></span>
                    <span class="text-[9px] text-slate-600" x-text="preview.sizeLabel"></span>
                </div>
                <button type="button" @click="removeFile(index)"
                    class="absolute top-1 right-1 w-5 h-5 rounded-full bg-black/60 text-white text-xs opacity-0 group-hover/file:opacity-100 transition">×</button>
            </div>
        </template>
    </div>

    <template x-for="(preview, index) in filePreviews" :key="'av-' + (preview.key || index) + '-' + (preview.revision || 0)">
        <div x-show="preview.isAudio || preview.isVideo"
            class="relative rounded-xl border border-white/10 bg-surface-200 p-2.5 flex flex-col"
            :style="preview.isVideo ? 'min-height: min(70dvh, calc(100dvh - 11rem))' : null">
            <div class="flex items-center justify-between gap-2 mb-1.5 shrink-0">
                <p class="text-[11px] text-slate-300 truncate" x-text="preview.name"></p>
                <button type="button" @click="removeFile(index)"
                    class="shrink-0 text-[11px] text-slate-400 hover:text-red-300 px-1">Remove</button>
            </div>
            <div class="min-h-0 flex flex-col" :class="preview.isVideo ? 'flex-1' : ''"
                x-data="ctMediaPlayer({
                    src: preview.url,
                    kind: preview.isVideo ? 'video' : 'audio',
                    compact: !!preview.isVideo,
                    editable: true,
                    previewIndex: index,
                    rate: preview.rate || 1,
                    trimStart: preview.trimStart || 0,
                    trimEnd: preview.trimEnd,
                })"
                x-effect="src = preview.url; kind = preview.isVideo ? 'video' : 'audio'; compact = !!preview.isVideo; $nextTick(() => bindMedia())"
                @media-edit-change="onMediaEditChange($event.detail)">
                @include('partials.ct-media-player')
            </div>
        </div>
    </template>
</div>

<div class="relative shrink-0 space-y-2" @click.outside="closeMentionMenu()">
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

    <p x-show="recording" x-cloak
        class="text-xs text-red-300 px-1">Recording… <span x-text="recordSeconds + 's'"></span> — click mic to stop</p>

    <div x-show="draftFormat === 'markdown' && showMarkdownGuide" x-cloak
        class="rounded-xl border border-white/10 bg-surface-300/90 px-3 py-2.5 text-[11px] text-slate-300 space-y-2 max-h-52 overflow-y-auto">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-semibold text-white">Markdown cheat sheet</p>
            <button type="button" @click="setMarkdownGuide(false)"
                class="text-[10px] text-slate-400 hover:text-white px-1.5 py-0.5 rounded border border-white/10">Hide</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 font-mono text-[10px] leading-relaxed">
            <p><span class="text-brand-300">**bold**</span> · <span class="text-brand-300">*italic*</span> · <span class="text-brand-300">`code`</span></p>
            <p><span class="text-brand-300">## Heading</span></p>
            <p><span class="text-brand-300">- item</span> · <span class="text-brand-300">1. item</span></p>
            <p><span class="text-brand-300">[link](https://…)</span></p>
            <p class="sm:col-span-2"><span class="text-brand-300">```js</span> … <span class="text-brand-300">```</span> fenced code</p>
            <p class="sm:col-span-2"><span class="text-brand-300">| a | b |</span> then <span class="text-brand-300">|---|---|</span> then rows</p>
        </div>
        <div class="border-t border-white/5 pt-2 space-y-1 text-[10px] text-slate-400">
            <p><span class="text-white font-medium">Line breaks (no empty line):</span></p>
            <p>End a line with <span class="text-brand-300 font-mono">\</span> then Shift+Enter → hard break</p>
            <p>Or end with <span class="text-brand-300 font-mono">two spaces</span> then Shift+Enter</p>
            <p><span class="text-white font-medium">Plain Shift+Enter</span> stays in the same paragraph (soft break)</p>
            <p><span class="text-white font-medium">Blank line</span> (Shift+Enter twice) → new paragraph</p>
        </div>
    </div>

    <div class="flex items-center justify-between gap-2 px-0.5">
        <div class="inline-flex rounded-lg border border-white/10 bg-surface-200 p-0.5 text-[10px] font-semibold">
            <button type="button" @click="setDraftFormat('plain')"
                class="px-2 py-1 rounded-md transition"
                :class="draftFormat === 'plain' ? 'bg-brand-500 text-white' : 'text-slate-400 hover:text-white'">Plain</button>
            <button type="button" @click="setDraftFormat('markdown')"
                class="px-2 py-1 rounded-md transition"
                :class="draftFormat === 'markdown' ? 'bg-brand-500 text-white' : 'text-slate-400 hover:text-white'">Markdown</button>
        </div>
        <button type="button" x-show="draftFormat === 'markdown'" x-cloak
            @click="setMarkdownGuide(!showMarkdownGuide)"
            class="text-[10px] font-semibold px-2 py-1 rounded-lg border border-white/10 text-slate-300 hover:bg-white/5 transition"
            x-text="showMarkdownGuide ? 'Hide guide' : 'Show guide'"></button>
    </div>

    <div x-show="draftFormat === 'markdown' && draft.trim()" x-cloak
        class="max-h-40 overflow-y-auto overscroll-contain rounded-xl border border-brand-500/20 bg-surface-300/80 px-4 py-2.5 text-sm text-slate-200 ct-md-body"
        x-html="draftMarkdownPreviewHtml()"></div>

    <div class="flex items-end gap-2 flex-wrap sm:flex-nowrap">
        <button type="button" @click="toggleMentionMenu()"
            class="shrink-0 w-10 h-10 rounded-xl border border-white/10 bg-surface-200 text-brand-400 hover:bg-white/5 transition font-bold"
            title="Mention someone">&#64;</button>
        <div class="flex-1 min-w-40 basis-[min(100%,12rem)] sm:basis-auto">
            <textarea x-ref="draftInput" x-model="draft" rows="1"
                @input="onDraftInput(); autoResizeDraft()"
                @keydown="onDraftKeydown($event)"
                @paste="onComposerPaste($event)"
                autocomplete="off"
                placeholder="{{ $composerPlaceholder }}"
                :disabled="sending"
                class="w-full bg-surface-200 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500 disabled:opacity-50 resize-none overflow-y-hidden min-h-10 leading-5"></textarea>
        </div>
        <label class="shrink-0 w-10 h-10 flex items-center justify-center rounded-xl border border-white/10 bg-surface-200 text-slate-400 hover:bg-white/5 hover:text-white cursor-pointer transition"
            title="Attach files (50 MB limit checked on send)">
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

    <p x-show="draftFormat === 'markdown'" x-cloak class="text-[10px] text-slate-500 px-1">
        Enter to send · Shift+Enter soft break (same paragraph) · <span class="font-mono">\</span>+Shift+Enter for &lt;br&gt;
    </p>
    <p x-show="draftFormat === 'plain'" x-cloak class="text-[10px] text-slate-500 px-1">
        Enter to send · Shift+Enter for a new line
    </p>
    <p x-show="sendError" x-cloak class="text-xs text-red-400" x-text="sendError"></p>
</div>
