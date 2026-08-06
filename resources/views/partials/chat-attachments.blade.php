{{-- Alpine attachment grid. Avoid nested x-if inside x-for (Alpine breaks on that). --}}
<template x-if="message.attachments && message.attachments.length">
    <div class="mb-2 flex flex-wrap gap-2">
        <template x-for="(attachment, ai) in message.attachments" :key="'att-' + (attachment.id || ai)">
            <a :href="attachment.url" target="_blank" rel="noopener"
                class="block overflow-hidden rounded-xl border border-white/10 bg-black/20 hover:bg-black/30 transition shrink-0"
                :class="attachment.is_image ? 'w-36 h-36 sm:w-40 sm:h-40' : 'w-36 sm:w-40 min-h-28'">
                <img x-show="attachment.is_image" :src="attachment.preview_url || attachment.url" :alt="attachment.name"
                    class="h-full w-full min-h-24 min-w-24 max-h-40 max-w-40 object-cover">
                <div x-show="!attachment.is_image" class="flex flex-col items-center justify-center gap-1.5 px-3 py-4 text-center min-h-28">
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
                </div>
            </a>
        </template>
    </div>
</template>
