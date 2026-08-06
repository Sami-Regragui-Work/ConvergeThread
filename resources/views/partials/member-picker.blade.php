@props([
    'members' => [],
    'selected' => [],
    'name' => 'user_ids',
    'open' => false,
    'direction' => 'dropdown',
])

<div x-data="memberPicker(@js($members), @js($selected), @js($name), @js($open), @js($direction))"
    class="relative w-full max-w-md">
    <div class="flex flex-wrap items-center gap-1.5 mb-2 min-h-5">
        <template x-for="id in selected" :key="'tag-' + id">
            <span
                class="inline-flex items-center gap-1 text-[10px] uppercase tracking-wide text-brand-300/90 bg-brand-500/10 px-1.5 py-0.5 rounded"
                x-text="labelFor(id)"></span>
        </template>
        <span x-show="!selected.length" class="text-xs text-slate-500">No members selected</span>
    </div>

    <button type="button" x-ref="toggleBtn" @click="toggleOpen()"
        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-white/10 bg-surface-200 text-sm text-slate-300 hover:bg-white/5 transition">
        <span x-text="direction === 'dropup' ? 'Pick members' : 'Add members'"></span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                :d="direction === 'dropup' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-ref="panel" @click.outside="open = false"
        class="z-100 max-h-64 overflow-hidden rounded-xl border border-white/10 bg-surface-300 shadow-xl flex flex-col fixed"
        :style="panelStyle">
        <div class="p-3 border-b border-white/5 space-y-2">
            <input type="text" x-model="search" placeholder="Search members…"
                class="w-full bg-surface-200 border border-white/10 text-white rounded-lg px-3 py-2 text-sm">
            <div class="flex gap-3 text-xs">
                <button type="button" @click="selectFiltered()" class="text-brand-400 hover:text-brand-300">Select filtered</button>
                <button type="button" @click="unselectFiltered()" class="text-slate-400 hover:text-white">Unselect filtered</button>
            </div>
        </div>
        <div class="overflow-y-auto flex-1 p-2 space-y-1">
            <template x-for="person in filtered()" :key="person.id">
                <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-white/5 cursor-pointer">
                    <input type="checkbox" :checked="selected.includes(person.id)" @change="toggle(person.id)">
                    <span class="text-sm text-slate-200" x-text="person.display_name"></span>
                    <span class="text-xs text-slate-500" x-text="person.username ? '@' + person.username : ''"></span>
                </label>
            </template>
        </div>
        <div class="p-2 border-t border-white/5">
            <button type="button" @click="open = false"
                class="w-full py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold">Done</button>
        </div>
        <template x-for="id in selected" :key="'hidden-'+id">
            <input type="hidden" :name="fieldName + '[]'" :value="id">
        </template>
    </div>
</div>

@once
    @push('scripts')
        @verbatim
        <script>
            function memberPicker(members, initialSelected, fieldName, startOpen, direction) {
                return {
                    members,
                    selected: [...initialSelected],
                    fieldName,
                    open: startOpen,
                    direction: direction || 'dropdown',
                    search: '',
                    panelStyle: '',
                    toggleOpen() {
                        this.open = !this.open;
                        if (this.open) {
                            this.$nextTick(() => this.positionPanel());
                        }
                    },
                    positionPanel() {
                        const btn = this.$refs.toggleBtn;
                        const panel = this.$refs.panel;
                        if (!btn || !panel) return;

                        const rect = btn.getBoundingClientRect();
                        const width = Math.max(rect.width, 280);
                        let top = rect.bottom + 8;
                        let bottom = 'auto';

                        if (this.direction === 'dropup') {
                            top = 'auto';
                            bottom = (window.innerHeight - rect.top + 8) + 'px';
                        }

                        this.panelStyle = [
                            'top:' + (top === 'auto' ? 'auto' : top + 'px'),
                            'bottom:' + bottom,
                            'left:' + rect.left + 'px',
                            'width:' + width + 'px',
                        ].join(';');
                    },
                    filtered() {
                        const q = this.search.toLowerCase();
                        return this.members.filter(p => {
                            if (!q) return true;
                            return (p.display_name || '').toLowerCase().includes(q)
                                || (p.username || '').toLowerCase().includes(q);
                        });
                    },
                    labelFor(id) {
                        const person = this.members.find(p => p.id === id);
                        return person?.display_name || person?.username || ('User #' + id);
                    },
                    toggle(id) {
                        if (this.selected.includes(id)) {
                            this.selected = this.selected.filter(x => x !== id);
                        } else {
                            this.selected.push(id);
                        }
                    },
                    selectFiltered() {
                        const ids = this.filtered().map(p => p.id);
                        this.selected = [...new Set([...this.selected, ...ids])];
                    },
                    unselectFiltered() {
                        const remove = new Set(this.filtered().map(p => p.id));
                        this.selected = this.selected.filter(id => !remove.has(id));
                    },
                };
            }
        </script>
        @endverbatim
    @endpush
@endonce
