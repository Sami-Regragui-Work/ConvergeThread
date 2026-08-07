{{-- Fullscreen attachment viewer (Alpine: mediaViewer). --}}
<template x-if="mediaViewer">
    <div
        class="fixed inset-0 z-200 flex items-center justify-center bg-zinc-950/90 p-3 sm:p-6"
        @keydown.escape.window="closeMediaViewer()"
        role="dialog"
        aria-modal="true"
        :aria-label="mediaViewer.name || 'Media viewer'"
    >
        <button type="button" class="absolute inset-0 cursor-default" aria-label="Close viewer" @click="closeMediaViewer()"></button>
        <div class="relative z-10 flex max-h-[min(92dvh,56rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-zinc-950 shadow-2xl shadow-black/50">
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-white/10 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-white" x-text="mediaViewer.name"></p>
                    <p class="truncate text-[11px] text-zinc-400" x-text="mediaViewer.meta"></p>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <a
                        :href="mediaViewer.url"
                        download
                        class="rounded-lg border border-white/15 px-2.5 py-1 text-[11px] font-semibold text-zinc-200 hover:bg-white/10"
                    >Download</a>
                    <button
                        type="button"
                        class="rounded-lg border border-white/15 px-2.5 py-1 text-[11px] font-semibold text-zinc-200 hover:bg-white/10"
                        @click="closeMediaViewer()"
                    >Close</button>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-auto bg-black/40 p-3 sm:p-4">
                <template x-if="mediaViewer.loading">
                    <div class="flex min-h-48 items-center justify-center text-sm text-zinc-400">Loading preview…</div>
                </template>
                <template x-if="!mediaViewer.loading && mediaViewer.type === 'image'">
                    <img :src="mediaViewer.url" :alt="mediaViewer.name" class="mx-auto max-h-[min(78dvh,48rem)] w-auto max-w-full object-contain">
                </template>
                <template x-if="!mediaViewer.loading && mediaViewer.type === 'video'">
                    <video :src="mediaViewer.url" controls playsinline class="mx-auto max-h-[min(78dvh,48rem)] w-full max-w-4xl"></video>
                </template>
                <template x-if="!mediaViewer.loading && mediaViewer.type === 'audio'">
                    <div class="mx-auto flex max-w-xl flex-col gap-3 rounded-xl border border-white/10 bg-zinc-900/80 p-6">
                        <p class="text-sm text-zinc-300" x-text="mediaViewer.name"></p>
                        <audio :src="mediaViewer.url" controls class="w-full"></audio>
                    </div>
                </template>
                <template x-if="!mediaViewer.loading && mediaViewer.type === 'pdf'">
                    <iframe :src="mediaViewer.url" class="h-[min(78dvh,48rem)] w-full rounded-lg border border-white/10 bg-white" title="PDF preview"></iframe>
                </template>
                <template x-if="!mediaViewer.loading && (mediaViewer.type === 'markdown' || mediaViewer.type === 'html')">
                    <div
                        class="ct-md mx-auto max-w-3xl rounded-xl border border-white/10 bg-zinc-950/90 px-4 py-5 text-sm text-zinc-100 sm:px-6"
                        x-html="mediaViewer.bodyHtml"
                    ></div>
                </template>
                <template x-if="!mediaViewer.loading && mediaViewer.type === 'text'">
                    <pre
                        class="mx-auto max-h-[min(78dvh,48rem)] max-w-4xl overflow-auto whitespace-pre-wrap wrap-break-word rounded-xl border border-white/10 bg-zinc-950/90 p-4 font-mono text-[12px] leading-relaxed text-zinc-200"
                        x-text="mediaViewer.bodyText"
                    ></pre>
                </template>
                <template x-if="!mediaViewer.loading && mediaViewer.type === 'file'">
                    <div class="mx-auto flex max-w-md flex-col items-center gap-3 rounded-xl border border-white/10 bg-zinc-900/80 px-6 py-10 text-center">
                        <p class="text-sm font-medium text-zinc-200" x-text="mediaViewer.name"></p>
                        <p class="text-xs text-zinc-400" x-text="mediaViewer.error || 'No in-app preview for this file type. Download to open it locally.'"></p>
                        <a
                            :href="mediaViewer.url"
                            download
                            class="mt-1 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-500"
                        >Download file</a>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
