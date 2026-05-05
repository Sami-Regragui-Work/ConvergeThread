@if ($errors->any())
    <div class="mb-4 bg-red-500/10 border border-red-500/20 rounded-xl px-5 py-4">
        <p class="text-sm font-semibold text-red-400 mb-2">Please fix the following errors:</p>
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li class="text-sm text-red-300 flex items-start gap-2">
                    <span class="mt-1 w-1.5 h-1.5 rounded-full bg-red-400 shrink-0"></span>
                    {{ $error }}
                </li>
            @endforeach
        </ul>
    </div>
@endif