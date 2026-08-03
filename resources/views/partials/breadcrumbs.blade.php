@if (!empty($breadcrumbs))
    <nav aria-label="Breadcrumb" class="mb-4 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        @foreach ($breadcrumbs as $index => $crumb)
            @if ($index > 0)
                <span aria-hidden="true">/</span>
            @endif

            @if (!empty($crumb['url']))
                <a href="{{ $crumb['url'] }}" class="transition hover:text-brand-400">{{ $crumb['label'] }}</a>
            @else
                <span class="text-slate-300">{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
