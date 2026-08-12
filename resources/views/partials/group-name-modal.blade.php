{{-- Group create / edit modal (Alpine). Keep classic pages as fallback. --}}
@auth
<div x-data="groupModal()" x-cloak>
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-200 flex items-center justify-center p-4" @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white" x-text="mode === 'create' ? 'New group' : 'Edit group'"></h2>
                    <button type="button" @click="close()" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Name</label>
                    <input type="text" x-model="name" @keydown.enter.prevent="submit()" maxlength="255"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                        placeholder="Group name" x-ref="nameInput">
                    <p x-show="error" x-cloak class="text-xs text-red-400 mt-2" x-text="error"></p>
                </div>
                <template x-if="mode === 'edit'">
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1.5">Accent color</label>
                        <div class="flex items-center gap-3">
                            <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                                <input type="checkbox" x-model="autoColor"
                                    class="rounded border-white/20 bg-surface-400 text-brand-500 focus:ring-brand-500/50">
                                Automatic
                            </label>
                            <template x-if="!autoColor">
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="color" :disabled="autoColor"
                                        class="h-8 w-12 rounded-lg border border-white/10 bg-surface-200 cursor-pointer">
                                    <input type="text" x-model="color" :disabled="autoColor" maxlength="7"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        class="w-28 bg-surface-200 border border-white/10 text-white rounded-xl px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition"
                                        placeholder="#6366f1">
                                </div>
                            </template>
                        </div>
                        <p class="text-[10px] text-slate-500 mt-1.5">Automatic picks a color from the group</p>
                    </div>
                </template>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()"
                        class="px-3 py-2 rounded-xl border border-white/10 text-slate-300 text-sm hover:bg-white/5">Cancel</button>
                    <button type="button" @click="submit()" :disabled="busy || !name.trim()"
                        class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold disabled:opacity-40">
                        <span x-text="busy ? 'Saving…' : (mode === 'create' ? 'Create' : 'Save')"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function groupModal() {
        return {
            open: false,
            mode: 'create',
            name: '',
            color: '#6366f1',
            autoColor: true,
            updateUrl: '',
            storeUrl: @js(route('groups.store')),
            error: '',
            busy: false,
            init() {
                window.addEventListener('ct-group-create', () => this.openCreate());
                window.addEventListener('ct-group-edit', (e) => this.openEdit(e.detail || {}));
            },
            openCreate() {
                this.mode = 'create';
                this.name = '';
                this.color = '#6366f1';
                this.autoColor = true;
                this.updateUrl = '';
                this.error = '';
                this.open = true;
                this.$nextTick(() => this.$refs.nameInput?.focus());
            },
            openEdit(detail) {
                this.mode = 'edit';
                this.name = detail.name || '';
                this.color = detail.color || '#6366f1';
                this.autoColor = !!detail.auto;
                this.updateUrl = detail.url || '';
                this.error = '';
                this.open = true;
                this.$nextTick(() => this.$refs.nameInput?.focus());
            },
            close() {
                this.open = false;
                this.busy = false;
            },
            async submit() {
                const value = (this.name || '').trim();
                if (!value) {
                    this.error = 'Name is required.';
                    return;
                }
                if (!this.autoColor && !/^#[0-9a-fA-F]{6}$/.test(this.color || '')) {
                    this.error = 'Enter a valid hex color, e.g. #6366f1.';
                    return;
                }
                this.busy = true;
                this.error = '';
                try {
                    const url = this.mode === 'create' ? this.storeUrl : this.updateUrl;
                    const method = 'POST';
                    const body = this.mode === 'create'
                        ? { name: value }
                        : { name: value, color: this.autoColor ? null : this.color, _method: 'PATCH' };
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
                        this.error = data.message || Object.values(data.errors || {}).flat()[0] || 'Could not save.';
                        this.busy = false;
                        return;
                    }
                    this.close();
                    if (this.mode === 'create' && data.group?.url) {
                        window.location.href = data.group.url;
                        return;
                    }
                    window.location.reload();
                } catch (e) {
                    this.error = 'Network error.';
                    this.busy = false;
                }
            },
        };
    }
    window.__openGroupCreate = () => window.dispatchEvent(new CustomEvent('ct-group-create'));
    window.__openGroupEdit = (detail) => window.dispatchEvent(new CustomEvent('ct-group-edit', { detail }));
</script>
@endauth
