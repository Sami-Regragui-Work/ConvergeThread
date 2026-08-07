{{-- Hierarchy create modal. Level/member editing stays on the page. --}}
<div x-data="hierarchyCreateModal()" x-cloak>
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-200 flex items-center justify-center p-4" @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white">New hierarchy</h2>
                    <button type="button" @click="close()" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Name</label>
                    <input type="text" x-model="name" @keydown.enter.prevent="submit()" maxlength="100"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                        placeholder="e.g. Engineering" x-ref="nameInput">
                </div>
                <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()"
                        class="px-3 py-2 rounded-xl border border-white/10 text-slate-300 text-sm hover:bg-white/5">Cancel</button>
                    <button type="button" @click="submit()" :disabled="busy || !name.trim()"
                        class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold disabled:opacity-40">
                        <span x-text="busy ? 'Creating…' : 'Create'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function hierarchyCreateModal() {
        return {
            open: false,
            name: '',
            error: '',
            busy: false,
            storeUrl: @js(route('hierarchies.store')),
            init() {
                window.addEventListener('ct-hierarchy-create', () => this.openCreate());
            },
            openCreate() {
                this.name = '';
                this.error = '';
                this.open = true;
                this.$nextTick(() => this.$refs.nameInput?.focus());
            },
            close() {
                this.open = false;
                this.busy = false;
            },
            async submit() {
                if (!this.name.trim()) {
                    this.error = 'Name is required.';
                    return;
                }
                this.busy = true;
                this.error = '';
                try {
                    const res = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ name: this.name.trim() }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.error = data.message || Object.values(data.errors || {}).flat()[0] || 'Could not create hierarchy.';
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
    window.__openHierarchyCreate = () => window.dispatchEvent(new CustomEvent('ct-hierarchy-create'));
</script>
