{{-- Alpine attachment grid. Avoid nested x-if inside x-for (Alpine breaks on that). --}}
<template x-if="message.attachments && message.attachments.length">
    <div class="mb-2 flex flex-wrap gap-2">
        <template x-for="(attachment, ai) in message.attachments" :key="'att-' + (attachment.id || ai)">
            <div class="overflow-hidden rounded-xl border border-white/10 bg-black/20 shrink-0"
                :class="(attachment.is_image || attachment.is_video) ? 'w-36 h-36 sm:w-40 sm:h-40' : 'w-36 sm:w-40 min-h-28'">
                <a x-show="attachment.is_image" :href="attachment.url" target="_blank" rel="noopener" class="block h-full w-full">
                    <img :src="attachment.preview_url || attachment.url" :alt="attachment.name"
                        class="h-full w-full min-h-24 min-w-24 max-h-40 max-w-40 object-cover">
                </a>
                <div x-show="attachment.is_video" class="relative h-full w-full bg-black">
                    <video :src="attachment.preview_url || attachment.url" class="h-full w-full object-cover" muted playsinline preload="metadata"></video>
                    <a :href="attachment.url" target="_blank" rel="noopener"
                        class="absolute inset-0 flex items-center justify-center bg-black/35 hover:bg-black/45 transition">
                        <span class="w-10 h-10 rounded-full bg-white/90 text-surface-400 flex items-center justify-center text-sm font-bold">▶</span>
                    </a>
                    <span x-show="attachment.size_label" class="absolute bottom-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-black/70 text-white"
                        x-text="attachment.size_label"></span>
                </div>
                <a x-show="!attachment.is_image && !attachment.is_video" :href="attachment.url" target="_blank" rel="noopener"
                    class="flex flex-col items-center justify-center gap-1.5 px-3 py-4 text-center min-h-28 h-full hover:bg-black/30 transition">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-[10px] font-bold uppercase tracking-wide"
                        :class="{
                            'bg-red-500/20 text-red-300': attachment.kind === 'pdf',
                            'bg-orange-500/20 text-orange-300': attachment.kind === 'ppt',
                            'bg-blue-500/20 text-blue-300': attachment.kind === 'doc',
                            'bg-emerald-500/20 text-emerald-300': attachment.kind === 'sheet',
                            'bg-white/10 text-slate-300': !['pdf','ppt','doc','sheet'].includes(attachment.kind)
                        }"
                        x-text="attachment.ext || 'FILE'"></span>
                    <span class="text-[11px] leading-tight truncate w-full"
                        :class="message.user_id === currentUserId ? 'text-white/90' : 'text-slate-300'"
                        x-text="attachment.name"></span>
                    <span x-show="attachment.size_label" class="text-[10px] text-slate-500" x-text="attachment.size_label"></span>
                </a>
            </div>
        </template>
    </div>
</template>
