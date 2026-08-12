@php
    $options ??= [];
    $param ??= 'sort';
    $dirParam ??= 'dir';
    $label ??= null;

    $current = (string) request()->query($param, '');
    $currentDir = strtolower((string) request()->query($dirParam, 'desc'));
    $selected = $current !== '' && isset($options["$current:$currentDir"]) ? "$current:$currentDir" : '';
@endphp

<div class="flex items-center gap-2">
    @if($label)
        <span class="text-xs text-slate-500 whitespace-nowrap">{{ $label }}</span>
    @endif
    <select
        class="max-w-full min-w-0 bg-surface-300 border border-white/10 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
        @change="(() => {
            const url = new URL(window.location.href);
            if ($el.value === '') {
                url.searchParams.delete('{{ $param }}');
                url.searchParams.delete('{{ $dirParam }}');
            } else {
                const [s, d] = $el.value.split(':');
                url.searchParams.set('{{ $param }}', s);
                url.searchParams.set('{{ $dirParam }}', d);
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        })()">
        <option value="">Default order</option>
        @foreach($options as $value => $option)
            <option value="{{ $value }}" @selected($selected === $value)>{{ $option }}</option>
        @endforeach
    </select>
</div>
