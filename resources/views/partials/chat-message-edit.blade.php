{{-- Inline message editor with attachment add/remove --}}
<div class="space-y-2 min-w-[16rem] sm:min-w-[20rem]">
    <input type="text" x-model="editDraft"
        class="w-full bg-surface-200 border border-white/10 text-white rounded-xl px-3 py-2 text-sm"
        placeholder="Message text (optional if files remain)">

    <div class="flex flex-wrap gap-2" x-show="editKeepAttachments.length || editFilePreviews.length">
        <template x-for="(attachment, ai) in editKeepAttachments" :key="'edit-keep-' + (attachment.id || ai)">
            <div class="relative group/edit rounded-lg border border-white/10 bg-black/40 overflow-hidden w-16 h-16 shrink-0">
                <img x-show="attachment.is_image && attachmentDisplayUrl(attachment)" x-cloak
                    :src="attachmentDisplayUrl(attachment)" class="h-full w-full object-cover" alt="">
                <div x-show="attachment.is_video" class="h-full w-full bg-black flex items-center justify-center text-white text-xs">▶</div>
                <div x-show="!attachment.is_image && !attachment.is_video"
                    class="h-full w-full flex flex-col items-center justify-center px-1 text-center">
                    <span class="text-[8px] font-bold uppercase text-slate-400" x-text="attachment.ext || 'FILE'"></span>
                </div>
                <button type="button" @click="removeEditAttachment(ai)"
                    class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-black/70 text-white text-[10px] leading-none">×</button>
            </div>
        </template>
        <template x-for="(preview, index) in editFilePreviews" :key="preview.key || ('new-' + index)">
            <div class="relative group/edit rounded-lg border border-brand-500/30 bg-surface-200 overflow-hidden w-16 h-16 shrink-0">
                <img x-show="preview.isImage" :src="preview.url" class="h-full w-full object-cover" alt="">
                <div x-show="preview.isVideo" class="h-full w-full bg-black flex items-center justify-center text-white text-xs">▶</div>
                <div x-show="preview.isAudio" class="h-full w-full flex items-center justify-center text-[8px] font-bold text-violet-300">AUD</div>
                <div x-show="!preview.isImage && !preview.isVideo && !preview.isAudio"
                    class="h-full w-full flex flex-col items-center justify-center px-1 text-center">
                    <span class="text-[8px] font-bold uppercase text-slate-400" x-text="preview.ext"></span>
                </div>
                <button type="button" @click="removeEditFile(index)"
                    class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-black/70 text-white text-[10px] leading-none">×</button>
            </div>
        </template>
    </div>

    <div class="flex flex-wrap items-center gap-2 justify-between">
        <label class="inline-flex items-center gap-1.5 text-[11px] text-slate-400 hover:text-white cursor-pointer px-2 py-1 rounded-lg border border-white/10 hover:bg-white/5 transition"
            title="Add files (50 MB limit checked on save)">
            <input type="file" x-ref="editFileInput" accept="*/*" multiple @change="onEditFilesChange($event)" class="sr-only">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
            </svg>
            Add files
        </label>
        <div class="flex gap-2">
            <button type="button" @click="cancelEdit()"
                class="text-xs text-slate-400 hover:text-white px-2 py-1">Cancel</button>
            <button type="button" @click="saveEdit(message.id)"
                :disabled="!canSaveEdit()"
                class="text-xs bg-brand-500 hover:bg-brand-600 disabled:opacity-40 text-white px-3 py-1 rounded-lg">Save</button>
        </div>
    </div>
    <p x-show="editError" x-cloak class="text-[11px] text-red-400" x-text="editError"></p>
</div>
