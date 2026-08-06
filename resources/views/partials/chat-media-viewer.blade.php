{{-- In-app media / document viewer --}}
<div x-show="mediaViewer" x-cloak
    class="fixed inset-0 z-[250] flex items-center justify-center p-3 sm:p-6"
    @keydown.escape.window="if (mediaViewer) closeMediaViewer()">
    <div class="absolute inset-0 bg-black/85" @click="closeMediaViewer()"></div>
    <div class="relative w-full max-w-5xl max-h-[90vh] flex flex-col rounded-2xl border border-white/10 bg-surface-300 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/5 shrink-0">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white truncate" x-text="mediaViewer?.name || 'Media'"></p>
                <p class="text-[11px] text-slate-500 truncate" x-text="mediaViewer?.meta || ''"></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a x-show="mediaViewer?.url" :href="mediaViewer?.url" download
                    class="px-2.5 py-1.5 rounded-lg border border-white/10 text-xs text-slate-300 hover:bg-white/5">Download</a>
                <button type="button" @click="closeMediaViewer()" class="px-2.5 py-1.5 rounded-lg border border-white/10 text-xs text-slate-300 hover:bg-white/5">Close</button>
            </div>
        </div>
        <div class="flex-1 min-h-0 overflow-auto bg-black flex items-center justify-center p-2">
            <img x-show="mediaViewer?.type === 'image'" x-cloak
                :src="mediaViewer?.url" :alt="mediaViewer?.name"
                class="max-w-full max-h-[75vh] object-contain">
            <video x-show="mediaViewer?.type === 'video'" x-cloak
                :src="mediaViewer?.url" controls playsinline
                class="max-w-full max-h-[75vh]"></video>
            <audio x-show="mediaViewer?.type === 'audio'" x-cloak
                :src="mediaViewer?.url" controls class="w-full max-w-lg"></audio>
            <iframe x-show="mediaViewer?.type === 'pdf'" x-cloak
                :src="mediaViewer?.url" class="w-full h-[75vh] bg-white rounded-lg" title="PDF"></iframe>
            <div x-show="mediaViewer?.type === 'file'" x-cloak class="text-center p-8 space-y-3">
                <p class="text-sm text-slate-300">No in-app preview for this file type.</p>
                <a :href="mediaViewer?.url" download
                    class="inline-flex px-4 py-2 rounded-xl bg-brand-500 text-white text-sm font-medium">Download file</a>
            </div>
        </div>
    </div>
</div>
