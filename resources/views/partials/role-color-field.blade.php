<div>
    <label class="block text-sm font-medium text-slate-300 mb-1.5">Role Color</label>
    <div class="flex items-center gap-3">
        <input type="color" id="role-color-picker" value="{{ old('color', $color ?? '#94a3b8') }}"
            class="h-10 w-14 rounded-lg border border-white/10 bg-surface-300 cursor-pointer"
            oninput="document.getElementById('role-color-hex').value = this.value">
        <input type="text" id="role-color-hex" name="color" value="{{ old('color', $color ?? '#94a3b8') }}"
            pattern="^#[0-9A-Fa-f]{6}$" placeholder="#94a3b8" required
            class="flex-1 bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition font-mono"
            oninput="document.getElementById('role-color-picker').value = this.value">
    </div>
    <p class="mt-1 text-xs text-slate-500">Used for display names and @role mentions in chat.</p>
    @error('color')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
</div>
