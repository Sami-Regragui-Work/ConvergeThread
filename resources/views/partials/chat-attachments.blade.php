{{-- Alpine attachment grid. Avoid nested x-if inside x-for (Alpine breaks on that). --}}
<template x-if="message.attachments && message.attachments.length">
    <div class="mb-2 flex flex-wrap gap-2">
        <template x-for="(attachment, ai) in message.attachments" :key="'att-' + (attachment.id || ai)">
            <div class="relative group/att overflow-hidden rounded-xl border border-white/10 shrink-0 bg-black/40"
                :class="{
                    'w-36 h-36 sm:w-40 sm:h-40': attachment.is_image || attachment.is_video,
                    'w-72 sm:w-80': attachment.is_audio,
                    'w-52 sm:w-60': !attachment.is_image && !attachment.is_video && !attachment.is_audio,
                }">
                <button type="button" @click.stop="downloadAttachment(attachment)"
                    class="absolute top-1.5 right-1.5 z-20 w-6 h-6 rounded-full bg-black/70 text-white opacity-0 group-hover/att:opacity-100 transition flex items-center justify-center hover:bg-black/90 pointer-events-none group-hover/att:pointer-events-auto"
                    title="Download">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </button>

                {{-- Image --}}
                <button type="button" x-show="attachment.is_image && attachmentDisplayUrl(attachment)" x-cloak
                    @click.stop="openMediaViewer(attachment)"
                    class="relative z-10 block h-full w-full bg-black text-left">
                    <img :src="attachmentDisplayUrl(attachment)" :alt="attachment.name"
                        class="h-full w-full object-cover">
                </button>
                <div x-show="attachment.is_image && !attachmentDisplayUrl(attachment)" x-cloak
                    class="h-full w-full flex items-center justify-center bg-black text-[10px] text-slate-500">
                    Decrypting…
                </div>

                {{-- Video --}}
                <button type="button" x-show="attachment.is_video && attachmentDisplayUrl(attachment)" x-cloak
                    @click.stop="openMediaViewer(attachment)"
                    class="relative z-10 h-full w-full bg-black text-left">
                    <video :src="attachmentDisplayUrl(attachment)" :poster="attachment.thumb_url || undefined"
                        class="h-full w-full object-cover pointer-events-none" muted playsinline preload="metadata"></video>
                    <span class="absolute inset-0 flex items-center justify-center bg-black/35">
                        <span class="w-10 h-10 rounded-full bg-white/90 text-surface-400 flex items-center justify-center text-sm font-bold">▶</span>
                    </span>
                    <span x-show="attachment.size_label" class="absolute bottom-1 left-1 text-[10px] px-1.5 py-0.5 rounded bg-black/70 text-white"
                        x-text="attachment.size_label"></span>
                </button>

                {{-- Audio (inline WhatsApp-style player) --}}
                <div x-show="attachment.is_audio" x-cloak class="p-2.5 space-y-1 w-full"
                    x-data="ctMediaPlayer({ src: '', kind: 'audio' })"
                    x-effect="
                        src = attachmentDisplayUrl(attachment) || '';
                        kind = 'audio';
                        $nextTick(() => bindMedia());
                    ">
                    @include('partials.ct-media-player')
                    <div class="flex items-center gap-2 min-w-0 pt-1">
                        <span class="shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-lg bg-violet-500/20 text-violet-300 text-[9px] font-bold">AUD</span>
                        <div class="min-w-0">
                            <p class="text-[11px] truncate"
                                :class="message.user_id === currentUserId ? 'text-white/90' : 'text-slate-200'"
                                x-text="attachment.name"></p>
                            <p class="text-[10px] text-slate-500" x-text="attachmentMetaLine(attachment)"></p>
                        </div>
                    </div>
                </div>

                {{-- Documents / other files (WhatsApp-style card) --}}
                <button type="button"
                    x-show="!attachment.is_image && !attachment.is_video && !attachment.is_audio" x-cloak
                    @click.stop="openMediaViewer(attachment)"
                    class="flex flex-col w-full h-full text-left hover:brightness-110 transition">
                    <div class="relative h-28 flex items-center justify-center overflow-hidden"
                        :class="{
                            'bg-red-950/50': attachment.kind === 'pdf',
                            'bg-orange-950/40': attachment.kind === 'ppt',
                            'bg-blue-950/40': attachment.kind === 'doc',
                            'bg-emerald-950/40': attachment.kind === 'sheet',
                            'bg-slate-900': !['pdf','ppt','doc','sheet'].includes(attachment.kind)
                        }">
                        <img x-show="attachment.thumb_url" x-cloak :src="attachment.thumb_url" alt=""
                            class="absolute inset-0 w-full h-full object-cover object-top">
                        <span x-show="!attachment.thumb_url" class="relative text-3xl font-black tracking-wide opacity-35"
                            :class="{
                                'text-red-300': attachment.kind === 'pdf',
                                'text-orange-300': attachment.kind === 'ppt',
                                'text-blue-300': attachment.kind === 'doc',
                                'text-emerald-300': attachment.kind === 'sheet',
                                'text-slate-300': !['pdf','ppt','doc','sheet'].includes(attachment.kind)
                            }"
                            x-text="attachment.ext || 'FILE'"></span>
                    </div>
                    <div class="flex items-center gap-2 px-2.5 py-2 bg-black/40 border-t border-white/5">
                        <span class="shrink-0 inline-flex items-center justify-center min-w-8 h-8 px-1 rounded-md text-[9px] font-bold uppercase"
                            :class="{
                                'bg-red-500 text-white': attachment.kind === 'pdf',
                                'bg-orange-500 text-white': attachment.kind === 'ppt',
                                'bg-blue-500 text-white': attachment.kind === 'doc',
                                'bg-emerald-500 text-white': attachment.kind === 'sheet',
                                'bg-white/15 text-slate-200': !['pdf','ppt','doc','sheet'].includes(attachment.kind)
                            }"
                            x-text="(attachment.ext || 'FILE').slice(0, 4)"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-semibold leading-tight truncate"
                                :class="message.user_id === currentUserId ? 'text-white' : 'text-slate-100'"
                                x-text="attachment.name"></p>
                            <p class="text-[10px] text-white/55 mt-0.5 truncate" x-text="attachmentMetaLine(attachment)"></p>
                        </div>
                    </div>
                </button>
            </div>
        </template>
    </div>
</template>
