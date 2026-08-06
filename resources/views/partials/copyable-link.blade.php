@props([
    'url',
    'label' => 'Link',
    'autoCopy' => true,
])

<div x-data="copyableLink(@js($url), @js($autoCopy))"
    class="rounded-lg border border-white/10 bg-black/30 overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-3 py-2 border-b border-white/5 bg-white/3">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</span>
        <button type="button" @click="copy()"
            class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
            <svg x-show="!copied" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <svg x-show="copied" x-cloak class="h-3.5 w-3.5 text-emerald-400" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
        </button>
    </div>
    <div class="relative group">
        <pre
            class="max-h-32 overflow-auto px-3 py-2.5 font-mono text-xs leading-relaxed text-slate-200 whitespace-pre-wrap break-all select-all">{{ $url }}</pre>
        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
            class="absolute right-2 top-2 rounded-md bg-surface-300/90 px-2 py-0.5 text-[10px] font-medium text-brand-400 opacity-0 transition group-hover:opacity-100 hover:text-brand-300">
            Open
        </a>
    </div>
    <p x-show="autoCopied" x-cloak class="border-t border-white/5 px-3 py-1.5 text-[11px] text-emerald-400/90">
        Link copied to clipboard — paste it in chat or email.
    </p>
</div>
