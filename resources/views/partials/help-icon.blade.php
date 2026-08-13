@php
    $hint = $hint ?? null;
    $position = $position ?? 'top';
@endphp
<span class="group relative inline-flex items-center" tabindex="0">
    <svg class="w-3.5 h-3.5 text-slate-400 hover:text-slate-100 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    @if($hint)
        <span role="tooltip" x-cloak
            class="hidden lg:group-hover:block absolute {{ $position === 'bottom' ? 'top-full mt-2' : 'bottom-full mb-2' }} left-1/2 -translate-x-1/2 w-64 px-3 py-2 rounded-lg bg-surface-100 border border-white/15 text-xs font-medium text-slate-100 shadow-2xl shadow-black/40 z-50 pointer-events-none opacity-100">
            {{ $hint }}
        </span>
    @endif
</span>
