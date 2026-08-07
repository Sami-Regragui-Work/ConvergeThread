{{-- Merge session create modal. Classic /merge-sessions/create kept as fallback. --}}
@can('create', App\Models\MergeSession::class)
@php
    $mergeGroups = $groups ?? \App\Models\Group::where('tenant_id', auth()->user()->tenant_id)->orderBy('name')->get(['id', 'name']);
@endphp
<div x-data="mergeSessionModal()" x-cloak>
    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-200 flex items-center justify-center p-4" @keydown.escape.window="close()">
            <div class="absolute inset-0 bg-black/70" @click="close()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-surface-300 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-white">New merge session</h2>
                    <button type="button" @click="close()" class="text-slate-400 hover:text-white text-sm">Close</button>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Group A</label>
                    <select x-model="group1Id"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                        x-ref="group1">
                        <option value="">— Select group —</option>
                        <template x-for="g in groups" :key="g.id">
                            <option :value="String(g.id)" x-text="g.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] text-slate-500 mb-1">Group B</label>
                    <select x-model="group2Id"
                        class="w-full bg-surface-200 border border-white/10 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                        <option value="">— Select group —</option>
                        <template x-for="g in groups" :key="'b-' + g.id">
                            <option :value="String(g.id)" x-text="g.name"></option>
                        </template>
                    </select>
                </div>
                <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="close()"
                        class="px-3 py-2 rounded-xl border border-white/10 text-slate-300 text-sm hover:bg-white/5">Cancel</button>
                    <button type="button" @click="submit()" :disabled="busy || !canSubmit()"
                        class="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold disabled:opacity-40">
                        <span x-text="busy ? 'Starting…' : 'Start session'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    function mergeSessionModal() {
        return {
            open: false,
            group1Id: '',
            group2Id: '',
            error: '',
            busy: false,
            storeUrl: @js(route('merge-sessions.store')),
            groups: @js($mergeGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values()),
            init() {
                window.addEventListener('ct-merge-create', () => this.openCreate());
            },
            openCreate() {
                this.group1Id = '';
                this.group2Id = '';
                this.error = '';
                this.open = true;
                this.$nextTick(() => this.$refs.group1?.focus());
            },
            close() {
                this.open = false;
                this.busy = false;
            },
            canSubmit() {
                return this.group1Id && this.group2Id && this.group1Id !== this.group2Id;
            },
            async submit() {
                if (!this.canSubmit()) {
                    this.error = 'Pick two different groups.';
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
                            group1_id: Number(this.group1Id),
                            group2_id: Number(this.group2Id),
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.error = data.message || Object.values(data.errors || {}).flat()[0] || 'Could not start session.';
                        this.busy = false;
                        return;
                    }
                    this.close();
                    if (data.session?.url) {
                        window.location.href = data.session.url;
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
    window.__openMergeCreate = () => window.dispatchEvent(new CustomEvent('ct-merge-create'));
</script>
@endcan
