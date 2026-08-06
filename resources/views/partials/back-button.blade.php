@if (\App\Support\BackNavigation::shouldShow())
    <a href="{{ \App\Support\BackNavigation::url() }}"
        class="inline-flex items-center justify-center p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition shrink-0"
        aria-label="Go back">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
    </a>
@endif
