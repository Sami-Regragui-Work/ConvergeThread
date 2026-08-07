{{-- Tenant role create/edit modal. Classic create/edit pages remain as fallback. --}}
@can('viewAny', App\Models\TenantRole::class)
@php
    $permissionOptions = App\Support\Permissions::all();
@endphp
<div x-data="tenantRoleModal()" x-cloak>
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-200 flex items-center justify-center p-4" @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <div class="relative w-full max-w-lg max-h-[90dvh] overflow-y-auto rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white" x-text="mode === 'edit' ? 'Edit role' : 'New role'"></h2>
                    <button type="button" @click="close()" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Role name</label>
                    <input type="text" x-model="name" maxlength="100" :readonly="isSystem"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                        :class="isSystem ? 'opacity-60 cursor-not-allowed' : ''"
                        placeholder="e.g. Project Lead" x-ref="nameInput">
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Color</label>
                    <div class="flex items-center gap-3">
                        <input type="color" x-model="color"
                            class="h-10 w-14 rounded-lg border border-white/10 bg-surface-200 cursor-pointer">
                        <input type="text" x-model="color" pattern="^#[0-9A-Fa-f]{6}$"
                            class="flex-1 bg-surface-200 border border-white/10 text-white rounded-xl px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                            placeholder="#6366f1">
                    </div>
                </div>
                <div x-show="!isSystem">
                    <label class="block text-[11px] text-slate-500 mb-1">Permissions</label>
                    <div class="space-y-2 max-h-56 overflow-y-auto rounded-xl border border-white/10 bg-surface-200/50 p-3">
                        <template x-for="perm in permissionOptions" :key="perm">
                            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer hover:text-white">
                                <input type="checkbox" :value="perm" x-model="permissions"
                                    class="rounded border-white/20 bg-surface-400 text-brand-500 focus:ring-brand-500/50">
                                <span class="font-mono text-xs" x-text="perm"></span>
                            </label>
                        </template>
                    </div>
                </div>
                <p x-show="isSystem" class="text-xs text-slate-500">System role permissions cannot be changed. You can customize the display color.</p>
                <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <a :href="fallbackUrl" class="text-xs text-slate-500 hover:text-slate-300" x-show="fallbackUrl">Open full page</a>
                    <div class="flex gap-2 ml-auto">
                        <button type="button" @click="close()"
                            class="px-3 py-2 rounded-xl border border-white/10 text-slate-300 text-sm hover:bg-white/5">Cancel</button>
                        <button type="button" @click="submit()" :disabled="busy || !canSubmit()"
                            class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold disabled:opacity-40">
                            <span x-text="busy ? 'Saving…' : (mode === 'edit' ? 'Save' : 'Create')"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function tenantRoleModal() {
        return {
            open: false,
            mode: 'create',
            roleId: null,
            name: '',
            color: '#6366f1',
            permissions: [],
            isSystem: false,
            error: '',
            busy: false,
            storeUrl: @js(route('tenant-roles.store')),
            updateUrlTemplate: @js(url('/tenant-roles/__ID__')),
            createPageUrl: @js(route('tenant-roles.create')),
            permissionOptions: @js($permissionOptions),
            init() {
                window.addEventListener('ct-tenant-role-create', () => this.openCreate());
                window.addEventListener('ct-tenant-role-edit', (e) => this.openEdit(e.detail || {}));
            },
            get fallbackUrl() {
                if (this.mode === 'edit' && this.roleId) {
                    return this.updateUrlTemplate.replace('__ID__', String(this.roleId)) + '/edit';
                }
                return this.createPageUrl;
            },
            openCreate() {
                this.mode = 'create';
                this.roleId = null;
                this.name = '';
                this.color = '#6366f1';
                this.permissions = [];
                this.isSystem = false;
                this.error = '';
                this.open = true;
                this.$nextTick(() => this.$refs.nameInput?.focus());
            },
            openEdit(role) {
                this.mode = 'edit';
                this.roleId = role.id;
                this.name = role.name || '';
                this.color = role.color || '#94a3b8';
                this.permissions = Array.isArray(role.permissions) ? [...role.permissions] : [];
                this.isSystem = !!role.is_system;
                this.error = '';
                this.open = true;
                this.$nextTick(() => {
                    if (!this.isSystem) this.$refs.nameInput?.focus();
                });
            },
            close() {
                this.open = false;
                this.busy = false;
            },
            canSubmit() {
                if (!this.name.trim() || !/^#[0-9A-Fa-f]{6}$/.test(this.color)) return false;
                if (!this.isSystem && !this.permissions.length) return false;
                return true;
            },
            async submit() {
                if (!this.canSubmit()) {
                    this.error = this.isSystem
                        ? 'Name and a valid color are required.'
                        : 'Name, color, and at least one permission are required.';
                    return;
                }
                this.busy = true;
                this.error = '';
                const body = {
                    name: this.name.trim(),
                    color: this.color,
                    permissions: this.isSystem ? undefined : this.permissions,
                };
                const url = this.mode === 'edit'
                    ? this.updateUrlTemplate.replace('__ID__', String(this.roleId))
                    : this.storeUrl;
                const method = this.mode === 'edit' ? 'PATCH' : 'POST';
                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.error = data.message || Object.values(data.errors || {}).flat()[0] || 'Could not save role.';
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
    window.__openTenantRoleCreate = () => window.dispatchEvent(new CustomEvent('ct-tenant-role-create'));
    window.__openTenantRoleEdit = (role) => window.dispatchEvent(new CustomEvent('ct-tenant-role-edit', { detail: role }));
</script>
@endcan
