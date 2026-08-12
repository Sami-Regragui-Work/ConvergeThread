@props([
    'hierarchy',
    'members',
    'groups',
    'tenantRoles',
])

@php
    $levels = $hierarchy->levels;

    // Candidate "add parent" targets per node: every node that is not the node
    // itself and not inside its subtree (linking would create a cycle).
    $linkTargets = $levels->mapWithKeys(function ($level) use ($levels) {
        $subtree = $level->subtreeIds($levels)->map(fn ($id) => (int) $id)->all();

        return [(int) $level->id => $levels
            ->reject(fn ($n) => in_array((int) $n->id, $subtree, true))
            ->map(fn ($n) => ['id' => (int) $n->id, 'name' => $n->label])
            ->values()
            ->all()];
    });

    $nodes = $levels->map(function ($level) use ($linkTargets) {
        return [
            'id' => (int) $level->id,
            'level' => (int) $level->level,
            'kind' => $level->kind,
            'parent_id' => $level->parent_id === null ? null : (int) $level->parent_id,
            'label' => $level->label,
            'group_name' => $level->group?->name,
            'group_member_count' => (int) $level->group?->active_members_count ?? 0,
            'role_name' => $level->role?->name,
            'members' => $level->members->map(fn ($m) => [
                'id' => (int) $m->id,
                'name' => $m->displayLabel(),
                'initial' => $m->avatarInitial(),
                'color' => $m->avatarColor(),
            ])->values()->all(),
            'link_targets' => $linkTargets[(int) $level->id],
            'urls' => [
                'link' => route('hierarchies.levels.link', $level),
                'addParent' => route('hierarchies.levels.add-parent', $level),
                'members' => route('hierarchies.levels.members', $level),
                'group' => route('hierarchies.levels.group', $level),
                'role' => route('hierarchies.levels.role', $level),
                'member' => route('hierarchies.levels.member', $level),
                'destroy' => route('hierarchies.levels.destroy', $level),
            ],
        ];
    })->values()->all();

    $payload = [
        'id' => (int) $hierarchy->id,
        'name' => $hierarchy->name,
        'kind' => $hierarchy->kind,
        'nodes' => $nodes,
    ];

    $options = [
        'members' => $members->map(fn ($m) => [
            'id' => (int) $m->id,
            'display_name' => $m->displayLabel(),
            'username' => $m->username,
            'avatar_color' => $m->avatarColor(),
            'initial' => $m->avatarInitial(),
        ])->values()->all(),
        'groups' => $groups->map(fn ($g) => [
            'id' => (int) $g->id,
            'name' => $g->name,
            'count' => (int) $g->active_members_count,
        ])->values()->all(),
        'roles' => $tenantRoles->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])->values()->all(),
        'addNodeUrl' => route('hierarchies.levels.store', $hierarchy),
        'destroyUrl' => route('hierarchies.destroy', $hierarchy),
    ];
@endphp

<div class="bg-surface-200 border border-white/5 rounded-2xl"
    x-data="hierarchyMap(@js($payload), @js($options))"
    x-init="$nextTick(() => { measure(); layout(); resizeWorld(); fit(); observeSize(); })">

    {{-- Card header --}}
    <div class="px-5 py-4 border-b border-white/5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 min-w-0">
            <h2 class="text-sm font-semibold text-white truncate" x-text="payload.name"></h2>
            <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-white/10 text-slate-400 shrink-0"
                x-text="payload.kind === 'role' ? 'Role' : 'Member'"></span>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" :action="options.addNodeUrl" class="shrink-0">
                @csrf
                <input type="hidden" name="parent_id" value="">
                <input type="hidden" name="kind" value="{{ $hierarchy->kind }}">
                <button type="submit"
                    class="text-xs text-brand-400 hover:text-brand-300 font-medium"
                    title="Add a new unlinked top-level node (level 0). Give it a parent from its actions.">+ Top-level node</button>
            </form>
            <form method="POST" :action="options.destroyUrl" class="shrink-0"
                @submit.prevent="$dispatch('confirm-action', { message: 'Delete this hierarchy and all its nodes?', form: $event.target })">
                @csrf @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-red-500/80 hover:text-red-400 hover:bg-red-500/10 px-2.5 py-1.5 rounded-lg transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Delete
                </button>
            </form>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="px-5 py-2.5 border-b border-white/5 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-1 bg-surface-300 rounded-xl p-1">
            <button type="button" @click="zoomBy(1 / 1.2)"
                class="w-7 h-7 rounded-lg text-slate-300 hover:bg-white/10 text-sm leading-none">−</button>
            <span class="w-12 text-center text-xs text-slate-400 tabular-nums" x-text="Math.round(zoom * 100) + '%'"></span>
            <button type="button" @click="zoomBy(1.2)"
                class="w-7 h-7 rounded-lg text-slate-300 hover:bg-white/10 text-sm leading-none">+</button>
        </div>
        <button type="button" @click="fit()"
            class="text-xs px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition">Fit</button>
        <button type="button" @click="resetLayout()"
            class="text-xs px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition">Reset layout</button>
        <p class="ml-auto text-[11px] text-slate-500 hidden md:block">Drag empty space to move · scroll to zoom · drag a node to place it anywhere</p>
    </div>

    {{-- Viewport --}}
    <div x-ref="viewport" class="relative overflow-hidden select-none touch-none"
        style="height: 540px"
        @mousedown="startPan($event)"
        @wheel.prevent="onWheel($event)"
        @mousemove.window="onMove($event)"
        @mouseup.window="onUp($event)"
        @touchstart="startTouch($event)"
        @touchmove.window="touchMove($event)"
        @touchend.window="touchEnd($event)"
        @touchcancel.window="touchEnd($event)"
        :class="drag ? 'cursor-grabbing' : 'cursor-grab'">

        {{-- World --}}
        <div class="absolute top-0 left-0"
            :style="worldStyle()">
            <svg class="absolute top-0 left-0 overflow-visible" pointer-events="none"
                :width="worldW" :height="worldH">
                <defs>
                    <marker id="edge-arrow-{{ $hierarchy->id }}" markerWidth="9" markerHeight="9" refX="8" refY="4.5"
                        orient="auto" markerUnits="userSpaceOnUse">
                        <path d="M0,0 L9,4.5 L0,9 L2.5,4.5 Z" fill="#94a3b8"></path>
                    </marker>
                </defs>
                <template x-for="node in nodes" :key="'edge-' + node.id">
                    <path x-show="node.parent_id != null" :d="edgeD(node)"
                        fill="none" stroke="#94a3b8" stroke-opacity="0.85" stroke-width="2.25"
                        marker-end="url(#edge-arrow-{{ $hierarchy->id }})" />
                </template>
            </svg>

            <template x-for="node in nodes" :key="node.id">
                <div :data-node="node.id"
                    class="absolute rounded-xl border bg-surface-300 p-3 space-y-2 transition-shadow duration-150 cursor-move"
                    :class="selectedId === node.id
                        ? 'border-brand-400/70 shadow-[0_0_0_3px_rgba(59,130,246,0.25)]'
                        : (node.kind === 'group' ? 'border-indigo-400/30' : (node.kind === 'role' ? 'border-purple-400/30' : 'border-white/10'))"
                    :style="nodeStyle(node)"
                    @mousedown.stop.prevent="startNodeDrag(node, $event)"
                    @touchstart.stop="startNodeTouch(node, $event)"
                    @click.stop="select(node)">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-brand-500/20 text-brand-300 text-[11px] font-bold shrink-0"
                            x-text="node.level"></span>
                        <span class="text-sm font-semibold text-white truncate" x-text="node.label"></span>
                        <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded shrink-0"
                            :class="node.kind === 'group'
                                ? 'bg-indigo-500/20 text-indigo-300'
                                : (node.kind === 'role' ? 'bg-purple-500/20 text-purple-300' : 'bg-emerald-500/20 text-emerald-300')"
                            x-text="node.kind === 'group' ? 'Group' : (node.kind === 'role' ? 'Role' : 'Member')"></span>
                    </div>

                    <template x-if="node.kind === 'group'">
                        <div class="text-xs text-slate-400" x-show="node.group_name">
                            Group: <span class="text-slate-200 font-medium" x-text="node.group_name"></span>
                            <span class="text-slate-500" x-text="' (' + node.group_member_count + ' active)'"></span>
                        </div>
                    </template>
                    <template x-if="node.kind === 'role'">
                        <div class="text-xs text-slate-400" x-show="node.role_name">
                            Role: <span class="text-slate-200 font-medium" x-text="node.role_name"></span>
                        </div>
                    </template>
                    <template x-if="node.kind === 'member'">
                        <div class="flex flex-wrap gap-1.5" :class="node.members.length ? '' : 'opacity-60'">
                            <template x-for="m in memberChips(node)" :key="m.id">
                                <span class="inline-flex items-center gap-1.5 text-[11px] px-2 py-1 rounded-lg bg-white/5 border border-white/10 text-slate-300">
                                    <span class="w-3.5 h-3.5 rounded-full flex items-center justify-center text-[8px] font-bold shrink-0"
                                        :style="'background-color:' + m.color + '33;color:' + m.color"
                                        x-text="m.initial"></span>
                                    <span class="max-w-24 truncate" x-text="m.name"></span>
                                </span>
                            </template>
                            <span x-show="memberExtra(node) > 0"
                                class="text-[11px] px-2 py-1 rounded-lg bg-white/5 border border-white/10 text-slate-400"
                                x-text="'+' + memberExtra(node)"></span>
                            <span x-show="!node.members.length" class="text-[11px] text-slate-500">No members yet</span>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Empty state --}}
        <div x-show="!nodes.length" x-cloak
            class="absolute inset-0 flex items-center justify-center text-slate-500 text-sm pointer-events-none">
            No nodes yet. Use “+ Top-level node” to start a tree.
        </div>

        {{-- Selected node actions --}}
        <div x-show="selectedId && selected()" x-cloak
            class="absolute inset-x-3 bottom-3 z-10 bg-surface-300 border border-white/10 rounded-2xl shadow-2xl p-4 space-y-3 max-h-72 overflow-y-auto touch-pan-y"
            @mousedown.stop @wheel.stop @touchstart.stop @touchmove.stop>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-brand-500/20 text-brand-300 text-[11px] font-bold shrink-0"
                        x-text="selected().level"></span>
                    <span class="text-sm font-semibold text-white truncate" x-text="selected().label"></span>
                    <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded shrink-0"
                        :class="selected().kind === 'group'
                            ? 'bg-indigo-500/20 text-indigo-300'
                            : (selected().kind === 'role' ? 'bg-purple-500/20 text-purple-300' : 'bg-emerald-500/20 text-emerald-300')"
                        x-text="selected().kind === 'group' ? 'Group' : (selected().kind === 'role' ? 'Role' : 'Member')"></span>
                </div>
                <button type="button" @click="selectedId = null" class="text-slate-400 hover:text-white text-sm shrink-0">Close</button>
            </div>

            <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                {{-- Add child --}}
                <div class="bg-surface-200 border border-white/5 rounded-xl p-3 space-y-2">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Add child</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <select x-model="childKind"
                            class="bg-surface-300 border border-white/10 text-slate-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                            <template x-for="opt in kindOptions()" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                        <button type="button" @click="addChild()" :disabled="busy"
                            class="text-xs px-2.5 py-1.5 rounded-lg bg-brand-500/20 text-brand-300 hover:bg-brand-500/30 transition disabled:opacity-40">
                            + Add child
                        </button>
                        <button type="button" @click="addSibling()" :disabled="busy"
                            class="text-xs px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition disabled:opacity-40">
                            Add sibling
                        </button>
                    </div>
                </div>

                {{-- Add parent --}}
                <div class="bg-surface-200 border border-white/5 rounded-xl p-3 space-y-2">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Add parent</p>
                    <button type="button" @click="addParentNewLevel()" :disabled="busy"
                        class="text-xs px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition disabled:opacity-40">
                        Insert new level above
                    </button>
                    <div class="flex flex-wrap items-center gap-2">
                        <select x-model="parentTargetId"
                            class="bg-surface-300 border border-white/10 text-slate-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                            <option value="" disabled x-text="'…or link under existing'"></option>
                            <template x-for="t in selected().link_targets" :key="t.id">
                                <option :value="t.id" x-text="t.name"></option>
                            </template>
                        </select>
                        <button type="button" @click="addParentExisting()" :disabled="busy || !parentTargetId"
                            class="text-xs px-2.5 py-1.5 rounded-lg bg-white/10 text-slate-200 hover:bg-white/20 transition disabled:opacity-40">
                            Set parent
                        </button>
                    </div>
                </div>

                {{-- Change type --}}
                <div class="bg-surface-200 border border-white/5 rounded-xl p-3 space-y-2">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Change node type</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <select x-model="typeDraft"
                            class="bg-surface-300 border border-white/10 text-slate-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                            <template x-for="opt in kindOptions()" :key="opt.value">
                                <option :value="opt.value" x-text="opt.label"></option>
                            </template>
                        </select>
                        <template x-if="typeDraft === 'group' && selected().kind !== 'group'">
                            <select x-model="typeGroupId"
                                class="bg-surface-300 border border-white/10 text-slate-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                                <option value="" disabled>Pick a group…</option>
                                <template x-for="g in options.groups" :key="g.id">
                                    <option :value="g.id" x-text="g.name + ' (' + g.count + ')'"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="typeDraft === 'role' && selected().kind !== 'role'">
                            <select x-model="typeRoleId"
                                class="bg-surface-300 border border-white/10 text-slate-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                                <option value="" disabled>Pick a role…</option>
                                <template x-for="r in options.roles" :key="r.id">
                                    <option :value="r.id" x-text="r.name"></option>
                                </template>
                            </select>
                        </template>
                        <button type="button" @click="changeType()" :disabled="busy || typeDraft === selected().kind"
                            class="text-xs px-2.5 py-1.5 rounded-lg bg-white/10 text-slate-200 hover:bg-white/20 transition disabled:opacity-40">
                            Apply
                        </button>
                    </div>
                    <p x-show="selected().kind === 'group' && typeDraft === 'group'"
                        class="text-[11px] text-slate-500">Members come from the group.</p>
                    <p x-show="selected().kind === 'member' && typeDraft === 'member'"
                        class="text-[11px] text-slate-500">Attach specific members below.</p>
                </div>

                {{-- Members --}}
                <div class="bg-surface-200 border border-white/5 rounded-xl p-3 space-y-2"
                    x-show="selected().kind === 'member'">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Node members</p>
                    <div class="max-h-40 overflow-y-auto space-y-1 pr-1">
                        <template x-for="m in options.members" :key="m.id">
                            <label class="flex items-center gap-2 px-1 py-1 rounded-lg hover:bg-white/5 cursor-pointer">
                                <input type="checkbox" :checked="memberIds.includes(m.id)" @change="toggleMember(m.id)">
                                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-semibold shrink-0"
                                    :style="'background-color:' + (m.avatar_color || '#64748b')"
                                    x-text="m.initial || '?'"></span>
                                <span class="text-xs text-slate-200 truncate" x-text="m.display_name"></span>
                            </label>
                        </template>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" @click="saveMembers()" :disabled="busy"
                            class="text-xs px-2.5 py-1.5 rounded-lg bg-brand-500/20 text-brand-300 hover:bg-brand-500/30 transition disabled:opacity-40">
                            Save node members
                        </button>
                        <span class="text-[11px] text-slate-500" x-text="memberIds.length + ' selected'"></span>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1 border-t border-white/10">
                <p class="text-[11px] text-slate-500">Deleting a node makes its children top-level nodes.</p>
                <button type="button" @click="deleteNode()" :disabled="busy"
                    class="text-xs font-medium text-red-500/80 hover:text-red-400 hover:bg-red-500/10 px-2.5 py-1.5 rounded-lg transition disabled:opacity-40">
                    Remove node
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function hierarchyMap(payload, options) {
        const NODE_W = 220;
        const MIN_H = 84;
        const H_GAP = 28;
        const V_GAP = 44;
        const ROOT_GAP = 90;
        const PAD = 60;

        return {
            payload,
            options,
            nodes: [],
            zoom: 1,
            pan: { x: 0, y: 0 },
            worldW: 800,
            worldH: 600,
            selectedId: null,
            childKind: payload.kind === 'role' ? 'role' : 'member',
            busy: false,
            error: '',
            drag: null,
            pinch: null,
            memberIds: [],
            parentTargetId: '',
            typeDraft: 'member',
            typeGroupId: '',
            typeRoleId: '',

            init() {
                this.nodes = (payload.nodes || []).map(n => ({
                    ...n,
                    x: 0,
                    y: 0,
                    w: NODE_W,
                    h: MIN_H,
                    pinned: false,
                }));

                this.layout();

                const saved = this.loadPositions();
                if (saved) {
                    this.nodes.forEach(n => {
                        if (saved[n.id]) {
                            n.pinned = true;
                            n.x = saved[n.id].x;
                            n.y = saved[n.id].y;
                        }
                    });
                }
            },

            observeSize() {
                if (typeof ResizeObserver === 'undefined') return;
                const viewport = this.$refs.viewport;
                if (!viewport) return;
                const ro = new ResizeObserver(() => {
                    if (!this.$refs.viewport || this.$refs.viewport.clientWidth === 0) return;
                    const w = this.$refs.viewport.clientWidth;
                    const h = this.$refs.viewport.clientHeight;
                    if (this._lastW === w && this._lastH === h) return;
                    this._lastW = w;
                    this._lastH = h;
                    this.fit();
                });
                ro.observe(viewport);
                this._lastW = viewport.clientWidth;
                this._lastH = viewport.clientHeight;
            },

            nodeById(id) {
                return this.nodes.find(n => n.id === id);
            },

            selected() {
                return this.nodeById(this.selectedId);
            },

            select(node) {
                this.selectedId = node.id;
                this.typeDraft = node.kind;
                this.typeGroupId = '';
                this.typeRoleId = '';
                this.parentTargetId = '';
                this.error = '';
                this.memberIds = (node.members || []).map(m => m.id);
            },

            kindOptions() {
                if (this.payload.kind === 'role') return [{ value: 'role', label: 'Role node' }];
                return [
                    { value: 'member', label: 'Member node' },
                    { value: 'group', label: 'Group node' },
                ];
            },

            memberChips(node) {
                return (node.members || []).slice(0, 4);
            },

            memberExtra(node) {
                return Math.max(0, (node.members || []).length - 4);
            },

            layout() {
                if (!this.nodes.length) return;

                const children = {};
                this.nodes.forEach(n => {
                    const key = n.parent_id == null ? 'root' : n.parent_id;
                    if (!children[key]) children[key] = [];
                    children[key].push(n);
                });

                const size = {};
                const sizeTree = (node) => {
                    const kids = children[node.id] || [];
                    kids.forEach(sizeTree);
                    let w = node.w;
                    let h = node.h;
                    if (kids.length) {
                        w = Math.max(node.w, kids.reduce((a, k) => a + size[k.id].w, 0) + H_GAP * (kids.length - 1));
                        h = node.h + V_GAP + Math.max(...kids.map(k => size[k.id].h));
                    }
                    size[node.id] = { w, h };
                };

                const place = (node, x, y) => {
                    if (!node.pinned) {
                        node.x = x;
                        node.y = y;
                    }
                    const kids = children[node.id] || [];
                    if (!kids.length) return;
                    const totalW = kids.reduce((a, k) => a + size[k.id].w, 0) + H_GAP * (kids.length - 1);
                    let cx = (node.pinned ? node.x : x) + (node.w - totalW) / 2;
                    const cy = (node.pinned ? node.y : y) + node.h + V_GAP;
                    kids.forEach(k => {
                        place(k, cx, cy);
                        cx += size[k.id].w + H_GAP;
                    });
                };

                let rx = 0;
                (children['root'] || []).forEach(root => {
                    sizeTree(root);
                    place(root, rx, 0);
                    rx += size[root.id].w + ROOT_GAP;
                });
            },

            measure() {
                this.nodes.forEach(n => {
                    const el = this.$root.querySelector('[data-node="' + n.id + '"]');
                    if (el) {
                        n.w = Math.max(NODE_W, el.offsetWidth);
                        n.h = Math.max(MIN_H, el.offsetHeight);
                    }
                });
            },

            bounds() {
                if (!this.nodes.length) return { minX: 0, minY: 0, w: 0, h: 0 };
                let minX = Infinity;
                let minY = Infinity;
                let maxX = -Infinity;
                let maxY = -Infinity;
                this.nodes.forEach(n => {
                    minX = Math.min(minX, n.x);
                    minY = Math.min(minY, n.y);
                    maxX = Math.max(maxX, n.x + n.w);
                    maxY = Math.max(maxY, n.y + n.h);
                });
                return { minX, minY, w: maxX - minX, h: maxY - minY };
            },

            resizeWorld() {
                const b = this.bounds();
                this.worldW = Math.max(800, b.w + PAD * 2);
                this.worldH = Math.max(600, b.h + PAD * 2);
            },

            fit() {
                const viewport = this.$refs.viewport;
                if (!viewport) return;
                const vw = viewport.clientWidth || 800;
                const vh = viewport.clientHeight || 540;
                const b = this.bounds();
                const bw = Math.max(b.w + PAD * 2, 200);
                const bh = Math.max(b.h + PAD * 2, 200);
                this.zoom = Math.min(vw / bw, vh / bh, 1.2);
                this.zoom = Math.max(0.2, Math.min(2, this.zoom));
                this.pan.x = vw / 2 - (b.minX + b.w / 2) * this.zoom;
                this.pan.y = vh / 2 - (b.minY + b.h / 2) * this.zoom;
            },

            zoomBy(factor) {
                const viewport = this.$refs.viewport;
                const cx = viewport ? viewport.clientWidth / 2 : 0;
                const cy = viewport ? viewport.clientHeight / 2 : 0;
                const z = Math.max(0.2, Math.min(2, this.zoom * factor));
                const k = z / this.zoom;
                this.pan.x = cx - (cx - this.pan.x) * k;
                this.pan.y = cy - (cy - this.pan.y) * k;
                this.zoom = z;
            },

            onWheel(e) {
                const rect = this.$refs.viewport.getBoundingClientRect();
                const cx = e.clientX - rect.left;
                const cy = e.clientY - rect.top;
                const z = Math.max(0.2, Math.min(2, this.zoom * (e.deltaY < 0 ? 1.12 : 1 / 1.12)));
                const k = z / this.zoom;
                this.pan.x = cx - (cx - this.pan.x) * k;
                this.pan.y = cy - (cy - this.pan.y) * k;
                this.zoom = z;
            },

            startNodeDrag(node, e) {
                this.select(node);
                this.drag = {
                    type: 'node',
                    id: node.id,
                    sx: e.clientX,
                    sy: e.clientY,
                    startX: node.x,
                    startY: node.y,
                };
            },

            startPan(e) {
                this.selectedId = null;
                this.drag = {
                    type: 'pan',
                    sx: e.clientX,
                    sy: e.clientY,
                    startPanX: this.pan.x,
                    startPanY: this.pan.y,
                };
            },

            onMove(e) {
                if (!this.drag) return;
                if (this.drag.type === 'pan') {
                    this.pan.x = this.drag.startPanX + (e.clientX - this.drag.sx);
                    this.pan.y = this.drag.startPanY + (e.clientY - this.drag.sy);
                } else {
                    const node = this.nodeById(this.drag.id);
                    if (!node) return;
                    node.x = this.drag.startX + (e.clientX - this.drag.sx) / this.zoom;
                    node.y = this.drag.startY + (e.clientY - this.drag.sy) / this.zoom;
                }
            },

            onUp() {
                if (this.drag && this.drag.type === 'node') {
                    this.savePositions();
                    this.resizeWorld();
                }
                this.drag = null;
            },

            touchPoint(e) {
                const t = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]);
                return t ? { clientX: t.clientX, clientY: t.clientY } : { clientX: 0, clientY: 0 };
            },

            startTouch(e) {
                if (e.touches.length >= 2) {
                    this.pinch = {
                        dist: Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY),
                        zoom: this.zoom,
                    };
                    this.drag = null;
                    return;
                }
                this.startPan(this.touchPoint(e));
            },

            touchMove(e) {
                if (this.pinch && e.touches.length >= 2) {
                    const a = e.touches[0];
                    const b = e.touches[1];
                    const dist = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
                    const cx = (a.clientX + b.clientX) / 2;
                    const cy = (a.clientY + b.clientY) / 2;
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    const vx = cx - rect.left;
                    const vy = cy - rect.top;
                    const z = Math.max(0.2, Math.min(2, this.pinch.zoom * (dist / Math.max(1, this.pinch.dist))));
                    const k = z / this.zoom;
                    this.pan.x = vx - (vx - this.pan.x) * k;
                    this.pan.y = vy - (vy - this.pan.y) * k;
                    this.zoom = z;
                    return;
                }
                if (!this.drag) return;
                this.onMove(this.touchPoint(e));
            },

            touchEnd() {
                this.pinch = null;
                this.onUp();
            },

            startNodeTouch(node, e) {
                if (e.touches.length >= 2) return;
                this.startNodeDrag(node, this.touchPoint(e));
            },

            edgeD(node) {
                const p = this.nodeById(node.parent_id);
                if (!p) return '';
                const x1 = p.x + p.w / 2;
                const y1 = p.y + p.h;
                const x2 = node.x + node.w / 2;
                const y2 = node.y;
                const ym = (y1 + y2) / 2;
                return 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + ym + ' ' + x2 + ',' + ym + ' ' + x2 + ',' + y2;
            },

            worldStyle() {
                return 'transform: translate(' + this.pan.x + 'px,' + this.pan.y + 'px) scale(' + this.zoom
                    + '); transform-origin: 0 0; width:' + this.worldW + 'px; height:' + this.worldH + 'px;';
            },

            nodeStyle(node) {
                return 'left:' + node.x + 'px; top:' + node.y + 'px; width:' + NODE_W + 'px;';
            },

            positionsKey() {
                return 'ct_hierarchy_positions_' + this.payload.id;
            },

            savePositions() {
                const data = {};
                this.nodes.forEach(n => {
                    data[n.id] = { x: Math.round(n.x), y: Math.round(n.y) };
                });
                try {
                    localStorage.setItem(this.positionsKey(), JSON.stringify(data));
                } catch (e) {}
            },

            loadPositions() {
                try {
                    const raw = localStorage.getItem(this.positionsKey());
                    return raw ? JSON.parse(raw) : null;
                } catch (e) {
                    return null;
                }
            },

            resetLayout() {
                try {
                    localStorage.removeItem(this.positionsKey());
                } catch (e) {}
                this.nodes.forEach(n => {
                    n.pinned = false;
                });
                this.layout();
                this.resizeWorld();
                this.fit();
            },

            async post(url, method, body) {
                this.busy = true;
                this.error = '';
                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(body),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.error = data.message || Object.values(data.errors || {}).flat()[0] || 'Request failed.';
                        this.busy = false;
                        return false;
                    }
                    return true;
                } catch (e) {
                    this.error = 'Network error.';
                    this.busy = false;
                    return false;
                }
            },

            async run(method, url, body) {
                const ok = await this.post(url, method, body);
                if (ok) window.location.reload();
            },

            addChild() {
                this.run('POST', this.options.addNodeUrl, { parent_id: this.selectedId, kind: this.childKind });
            },

            addSibling() {
                const node = this.selected();
                if (!node) return;
                this.run('POST', this.options.addNodeUrl, {
                    parent_id: node.parent_id == null ? '' : node.parent_id,
                    kind: this.childKind,
                });
            },

            addParentNewLevel() {
                const node = this.selected();
                if (node) this.run('PATCH', node.urls.addParent, {});
            },

            addParentExisting() {
                const node = this.selected();
                if (!node || !this.parentTargetId) return;
                this.run('PATCH', node.urls.link, { parent_id: this.parentTargetId });
            },

            toggleMember(id) {
                if (this.memberIds.includes(id)) {
                    this.memberIds = this.memberIds.filter(x => x !== id);
                } else {
                    this.memberIds.push(id);
                }
            },

            saveMembers() {
                const node = this.selected();
                if (node) this.run('PATCH', node.urls.members, { user_ids: this.memberIds });
            },

            changeType() {
                const node = this.selected();
                if (!node || this.typeDraft === node.kind) return;
                if (this.typeDraft === 'member') {
                    this.run('PATCH', node.urls.member, {});
                } else if (this.typeDraft === 'group') {
                    if (!this.typeGroupId) {
                        this.error = 'Pick a group first.';
                        return;
                    }
                    this.run('PATCH', node.urls.group, { group_id: this.typeGroupId });
                } else if (this.typeDraft === 'role') {
                    if (!this.typeRoleId) {
                        this.error = 'Pick a role first.';
                        return;
                    }
                    this.run('PATCH', node.urls.role, { role_id: this.typeRoleId });
                }
            },

            deleteNode() {
                const node = this.selected();
                if (!node) return;
                this.$dispatch('confirm-action', {
                    message: 'Remove this node? Its children become top-level nodes.',
                    confirmLabel: 'Remove node',
                    onConfirm: () => this.run('DELETE', node.urls.destroy, {}),
                });
            },
        };
    }
</script>
