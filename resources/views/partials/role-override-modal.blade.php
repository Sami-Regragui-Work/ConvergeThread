{{-- Group role-override create modal. Only group-scoped permissions are offered; permissions already granted by the base role are shown checked and disabled. --}}
@can('create', [App\Models\GroupRoleOverride::class, $group])
@php
    $permissionOptions = collect(App\Support\Permissions::groupScopedOptions())
        ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
        ->values();
@endphp
<div x-data="roleOverrideModal()" x-cloak>
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-200 flex items-center justify-center p-4" @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <div class="relative w-full max-w-lg max-h-[90dvh] overflow-y-auto rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white">Create override</h2>
                    <button type="button" @click="close()" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Base tenant role</label>
                    <select x-model="tenantRoleId" @change="onRoleChange()"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                        <option value="">— Select role —</option>
                        <template x-for="role in tenantRoles" :key="role.id">
                            <option :value="String(role.id)" x-text="role.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Group permissions</label>
                    <p class="text-[11px] text-slate-500 mb-2">
                        Checked-but-disabled permissions come from the base role and already apply to this member.
                        Check additional permissions to grant them inside this group only.
                    </p>
                    <div class="space-y-2 max-h-56 overflow-y-auto rounded-xl border border-white/10 bg-surface-200/50 p-3">
                        <template x-for="perm in permissionOptions" :key="perm.value">
                            <label class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-300 cursor-pointer hover:text-white"
                                :class="isInherited(perm.value) ? 'opacity-60 cursor-not-allowed' : ''">
                                <input type="checkbox"
                                    :checked="isInherited(perm.value) || permissions.includes(perm.value)"
                                    :disabled="isInherited(perm.value)"
                                    @change="togglePermission(perm.value)"
                                    class="shrink-0 rounded border-white/20 bg-surface-400 text-brand-500 focus:ring-brand-500/50">
                                <span class="text-xs min-w-0" x-text="perm.label"></span>
                                <code class="ml-auto font-mono text-[10px] text-slate-500 truncate min-w-0"
                                    x-text="perm.value"></code>
                                <span class="shrink-0 text-[10px] text-slate-500" x-show="isInherited(perm.value)">from base role</span>
                            </label>
                        </template>
                    </div>
                </div>
                <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()"
                        class="px-3 py-2 rounded-xl border border-white/10 text-slate-300 text-sm hover:bg-white/5">Cancel</button>
                    <button type="button" @click="submit()" :disabled="busy || !tenantRoleId"
                        class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold disabled:opacity-40">
                        <span x-text="busy ? 'Saving…' : 'Add override'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function roleOverrideModal() {
        return {
            open: false,
            tenantRoleId: '',
            permissions: [],
            inherited: [],
            error: '',
            busy: false,
            storeUrl: @js(route('groups.role-overrides.store', $group)),
            tenantRoles: @js($tenantRoles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values()),
            basePermissions: @js($basePermissions),
            permissionOptions: @js($permissionOptions),
            init() {
                window.addEventListener('ct-role-override-create', () => this.openCreate());
            },
            openCreate() {
                this.tenantRoleId = '';
                this.permissions = [];
                this.inherited = [];
                this.error = '';
                this.open = true;
            },
            close() {
                this.open = false;
                this.busy = false;
            },
            onRoleChange() {
                this.inherited = this.basePermissions[this.tenantRoleId] || [];
            },
            isInherited(value) {
                return this.inherited.includes(value);
            },
            togglePermission(value) {
                if (this.inherited.includes(value)) {
                    return;
                }
                const idx = this.permissions.indexOf(value);
                if (idx >= 0) {
                    this.permissions.splice(idx, 1);
                } else {
                    this.permissions.push(value);
                }
            },
            async submit() {
                if (!this.tenantRoleId) {
                    this.error = 'Select a base tenant role.';
                    return;
                }
                this.busy = true;
                this.error = '';
                try {
                    const payload = { tenant_role_id: Number(this.tenantRoleId) };
                    if (this.permissions.length) payload.permissions = this.permissions;
                    const res = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.error = data.message || Object.values(data.errors || {}).flat()[0] || 'Could not create override.';
                        this.busy = false;
                        return;
                    }
                    this.close();
                    window.location.reload();
                } catch (e) {
                    this.error = 'Network error.';
                    this.busy = false;
                }
            },
        };
    }
    window.__openRoleOverrideCreate = () => window.dispatchEvent(new CustomEvent('ct-role-override-create'));
</script>
@endcan
