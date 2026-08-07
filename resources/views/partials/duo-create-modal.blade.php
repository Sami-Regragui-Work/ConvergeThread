{{-- Duo create modal (Alpine). Classic inline form kept as fallback on create failures. --}}
@can('create', [App\Models\Duo::class, $group])
<div x-data="duoCreateModal()" x-cloak>
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-200 flex items-center justify-center p-4" @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white">New duo</h2>
                    <button type="button" @click="close()" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Name</label>
                    <input type="text" x-model="name" @keydown.enter.prevent="submit()" maxlength="255"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                        placeholder="e.g. Design sync" x-ref="nameInput">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">User 1</label>
                        <select x-model="user1Id"
                            class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                            <option value="">— Select —</option>
                            <template x-for="m in members" :key="m.id">
                                <option :value="String(m.id)" x-text="m.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-500 mb-1">User 2</label>
                        <select x-model="user2Id"
                            class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                            <option value="">— Select —</option>
                            <template x-for="m in members" :key="'u2-' + m.id">
                                <option :value="String(m.id)" x-text="m.label"></option>
                            </template>
                        </select>
                    </div>
                </div>
                <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()"
                        class="px-3 py-2 rounded-xl border border-white/10 text-slate-300 text-sm hover:bg-white/5">Cancel</button>
                    <button type="button" @click="submit()" :disabled="busy || !canSubmit()"
                        class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold disabled:opacity-40">
                        <span x-text="busy ? 'Creating…' : 'Create'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function duoCreateModal() {
        return {
            open: false,
            name: '',
            user1Id: '',
            user2Id: '',
            error: '',
            busy: false,
            storeUrl: @js(route('groups.duos.store', $group)),
            members: @js($members->map(fn ($m) => [
                'id' => $m->id,
                'label' => $m->display_name ?? $m->email,
            ])->values()),
            init() {
                window.addEventListener('ct-duo-create', () => this.openCreate());
            },
            openCreate() {
                this.name = '';
                this.user1Id = '';
                this.user2Id = '';
                this.error = '';
                this.open = true;
                this.$nextTick(() => this.$refs.nameInput?.focus());
            },
            close() {
                this.open = false;
                this.busy = false;
            },
            canSubmit() {
                return this.name.trim() && this.user1Id && this.user2Id && this.user1Id !== this.user2Id;
            },
            async submit() {
                if (!this.canSubmit()) {
                    this.error = 'Name and two different members are required.';
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
                        body: JSON.stringify({
                            name: this.name.trim(),
                            user1_id: Number(this.user1Id),
                            user2_id: Number(this.user2Id),
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.error = data.message || Object.values(data.errors || {}).flat()[0] || 'Could not create duo.';
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
    window.__openDuoCreate = () => window.dispatchEvent(new CustomEvent('ct-duo-create'));
</script>
@endcan
