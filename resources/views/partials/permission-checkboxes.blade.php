@php
    use App\Support\Permissions;
    $selected = old('permissions', $selected ?? []);
@endphp

<div class="space-y-2 max-h-64 overflow-y-auto rounded-xl border border-white/10 bg-surface-300/50 p-3">
    @foreach (Permissions::all() as $permission)
        <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer hover:text-white">
            <input type="checkbox" name="permissions[]" value="{{ $permission }}"
                @checked(in_array($permission, $selected, true))
                class="rounded border-white/20 bg-surface-400 text-brand-500 focus:ring-brand-500/50">
            <span class="font-mono text-xs">{{ $permission }}</span>
        </label>
    @endforeach
</div>
@error('permissions')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
