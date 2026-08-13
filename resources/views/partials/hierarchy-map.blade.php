@props([
    'hierarchy',
    'members',
    'groups',
    'tenantRoles',
])

@php
    $payload = app(\App\Services\RoleHierarchyService::class)->mapPayload($hierarchy);
    $payload['mapUrl'] = route('hierarchies.map', $hierarchy);

    // Levels are loaded by mapPayload; the SVG below only needs them for the
    // shared edge marker id, edges themselves are rendered client-side.
    $levels = $hierarchy->levels;

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
        'mapUrl' => route('hierarchies.map', $hierarchy),
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
            <button type="button" @click="addTopLevel()" :disabled="busy"
                class="text-xs text-brand-400 hover:text-brand-300 font-medium disabled:opacity-40"
                title="Add a new unlinked top-level node (level 0). Give it a parent from its actions.">+ Top-level node</button>
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
        <p class="ml-auto text-[11px] text-slate-500 hidden md:block">Drag empty space to move · scroll to zoom · Shift+drag to select · Ctrl+click to multi-select · drag a node to place it anywhere</p>
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
                    <marker id="edge-arrow-{{ $hierarchy->id }}" markerWidth="12" markerHeight="12" refX="10" refY="6"
                        orient="auto" markerUnits="userSpaceOnUse">
                        <path d="M1,1 L12,6 L1,11 L3.5,6 Z" fill="#94a3b8"></path>
                    </marker>
                </defs>
                <template x-for="node in nodes" :key="'edge-' + node.id">
                    <path x-show="node.parent_id" :d="edgeD(node)"
                        fill="none" stroke="#94a3b8" stroke-opacity="1" stroke-width="2.5"
                        marker-end="url(#edge-arrow-{{ $hierarchy->id }})" />
                </template>
            </svg>

            <template x-for="node in nodes" :key="node.id">
                <div :data-node="node.id"
                    class="absolute rounded-xl border bg-surface-300 p-3 space-y-2 transition-shadow duration-150 cursor-move"
                    :class="selectedIds.includes(node.id)
                        ? (selectedId === node.id
                            ? 'border-brand-400/70 shadow-[0_0_0_3px_rgba(59,130,246,0.25)]'
                            : 'border-brand-400/40 shadow-[0_0_0_2px_rgba(59,130,246,0.18)]')
                        : (node.kind === 'group' ? 'border-indigo-400/30' : (node.kind === 'role' ? 'border-purple-400/30' : 'border-white/10'))"
                    :style="nodeStyle(node)"
                    @mousedown.stop.prevent="startNodeDrag(node, $event)"
                    @touchstart.stop="startNodeTouch(node, $event)"
                    @click.stop="select(node, $event)">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-brand-500/20 text-brand-300 text-[11px] font-bold shrink-0"
                            x-text="node.level"></span>
                        <span class="text-sm font-semibold text-white truncate min-w-0 flex-1" x-text="node.label"></span>
                        <span class="inline-flex shrink-0 items-center px-1.5 py-0.5 rounded bg-brand-500/15 text-brand-300 text-[10px] font-semibold"
                            x-text="nodeTag(node)"></span>
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

        {{-- Marquee selection zone --}}
        <div x-show="marquee" x-cloak
            class="absolute z-20 pointer-events-none border border-brand-400/80 bg-brand-500/15"
            :style="marqueeStyle()"></div>

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
                    <span x-show="selectedIds.length > 1" x-cloak
                        class="text-[10px] text-slate-400 bg-white/5 border border-white/10 px-1.5 py-0.5 rounded shrink-0"
                        x-text="selectedIds.length + ' selected'"></span>
                </div>
                <button type="button" @click="selectedId = null" class="text-slate-400 hover:text-white text-sm shrink-0">Close</button>
            </div>

            <p x-show="error" x-cloak class="text-xs text-red-400" x-text="error"></p>

            <div class="bg-surface-200 border border-white/5 rounded-xl p-3 space-y-2">
                <p class="text-[11px] uppercase tracking-wide text-slate-500">Node tag</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-slate-400">Level label:</span>
                    <span class="text-xs font-semibold text-white" x-text="selected().label"></span>
                    <span class="inline-flex px-1.5 py-0.5 rounded bg-brand-500/15 text-brand-300 text-[10px] font-semibold"
                        x-text="nodeTag(selected())"></span>
                    <input type="text" x-model="tagDraft" maxlength="40" @keydown.enter.prevent="saveTag()"
                        class="flex-1 min-w-32 bg-surface-300 border border-white/10 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition"
                        placeholder="(1)">
                    <button type="button" @click="saveTag()" :disabled="busy"
                        class="text-xs px-2.5 py-1.5 rounded-lg bg-brand-500/20 text-brand-300 hover:bg-brand-500/30 transition disabled:opacity-40">
                        Save tag
                    </button>
                </div>
                <p class="text-[11px] text-slate-500">Shown next to the level label to tell nodes apart. Must be unique per level.</p>
            </div>

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
                                <option :value="t.id" x-text="displayFor(t.id)"></option>
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

                {{-- Role picker (role nodes) --}}
                <div class="bg-surface-200 border border-white/5 rounded-xl p-3 space-y-2"
                    x-show="selected().kind === 'role'">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Node role</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <select x-model="typeRoleId"
                            class="flex-1 min-w-32 bg-surface-300 border border-white/10 text-slate-300 text-xs rounded-lg px-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                            <option value="" disabled>Pick a role…</option>
                            <template x-for="r in options.roles" :key="r.id">
                                <option :value="r.id" x-text="r.name"></option>
                            </template>
                        </select>
                        <button type="button" @click="saveRole()" :disabled="busy || !typeRoleId"
                            class="text-xs px-2.5 py-1.5 rounded-lg bg-brand-500/20 text-brand-300 hover:bg-brand-500/30 transition disabled:opacity-40">
                            Save role
                        </button>
                    </div>
                    <p x-show="selected().role_name" class="text-[11px] text-slate-500">
                        Current: <span class="text-slate-200 font-medium" x-text="selected().role_name"></span>
                    </p>
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
        const H_GAP = 40;
        const V_GAP = 56;
        const ROOT_GAP = 120;
        const PAD = 64;

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
            tagDraft: '',
            selectedIds: [],
            marquee: null,
            _justDragged: false,

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

            isSelected(id) {
                return this.selectedIds.includes(id);
            },

            populatePanel(node) {
                this.typeDraft = node.kind;
                this.typeGroupId = '';
                this.typeRoleId = node.role_id ? node.role_id : '';
                this.parentTargetId = '';
                this.error = '';
                this.memberIds = (node.members || []).map(m => m.id);
                this.tagDraft = node.tag || '';
            },

            selectOnly(node) {
                this.selectedIds = [node.id];
                this.selectedId = node.id;
                this.populatePanel(node);
            },

            select(node, event) {
                if (this._justDragged) {
                    this._justDragged = false;
                    return;
                }
                if (event && (event.ctrlKey || event.metaKey)) {
                    if (this.isSelected(node.id)) {
                        this.selectedIds = this.selectedIds.filter(id => id !== node.id);
                        if (this.selectedId === node.id) {
                            this.selectedId = this.selectedIds[this.selectedIds.length - 1] || null;
                            const next = this.selectedId ? this.nodeById(this.selectedId) : null;
                            if (next) this.populatePanel(next);
                        }
                    } else {
                        this.selectedIds.push(node.id);
                        this.selectedId = node.id;
                        this.populatePanel(node);
                    }
                    return;
                }
                this.selectOnly(node);
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

            siblingRank(node) {
                const sibs = this.nodes.filter(o => o.level === node.level).sort((a, b) => a.id - b.id);
                return sibs.findIndex(o => o.id === node.id) + 1;
            },

            nodeTag(node) {
                const tag = (node.tag || '').trim();
                return tag || '(' + this.siblingRank(node) + ')';
            },

            nodeDisplay(node) {
                return (node.label || '') + ' ' + this.nodeTag(node);
            },

            displayFor(id) {
                const n = this.nodeById(id);
                return n ? this.nodeDisplay(n) : '';
            },

            async saveTag() {
                const node = this.selected();
                if (!node) return;
                const tag = (this.tagDraft || '').trim();
                if (!tag) {
                    this.error = 'Tag cannot be empty.';
                    return;
                }
                const duplicate = this.nodes.find(o => o.id !== node.id && o.level === node.level && this.nodeTag(o) === tag);
                if (duplicate) {
                    this.error = 'Another node at this level already uses "'.concat(tag, '".');
                    return;
                }
                const ok = await this.post(node.urls.tag, 'PATCH', { tag });
                if (!ok) return;
                node.tag = tag;
                this.tagDraft = tag;
                this.error = '';
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
                if (!(e.ctrlKey || e.metaKey) && !this.isSelected(node.id)) {
                    this.selectOnly(node);
                }
                const ids = this.isSelected(node.id) ? [...this.selectedIds] : [node.id];
                const startPos = {};
                ids.forEach(id => {
                    const n = this.nodeById(id);
                    if (n) startPos[id] = { x: n.x, y: n.y };
                });
                this.drag = {
                    type: 'node',
                    ids,
                    sx: e.clientX,
                    sy: e.clientY,
                    startPos,
                };
            },

            startPan(e) {
                if (e.shiftKey) {
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    this.marquee = { x1: x, y1: y, x2: x, y2: y, additive: e.ctrlKey || e.metaKey };
                    this.drag = { type: 'marquee' };
                    return;
                }
                this.selectedId = null;
                this.selectedIds = [];
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
                } else if (this.drag.type === 'marquee') {
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    this.marquee.x2 = e.clientX - rect.left;
                    this.marquee.y2 = e.clientY - rect.top;
                } else {
                    const dx = (e.clientX - this.drag.sx) / this.zoom;
                    const dy = (e.clientY - this.drag.sy) / this.zoom;
                    this.drag.ids.forEach(id => {
                        const node = this.nodeById(id);
                        if (!node || !this.drag.startPos[id]) return;
                        node.x = this.drag.startPos[id].x + dx;
                        node.y = this.drag.startPos[id].y + dy;
                    });
                }
            },

            onUp() {
                if (!this.drag) return;
                if (this.drag.type === 'node') {
                    this._justDragged = true;
                    this.savePositions();
                    this.resizeWorld();
                } else if (this.drag.type === 'marquee') {
                    this.commitMarquee();
                }
                this.drag = null;
                this.marquee = null;
            },

            marqueeStyle() {
                if (!this.marquee) return 'display:none;';
                const x = Math.min(this.marquee.x1, this.marquee.x2);
                const y = Math.min(this.marquee.y1, this.marquee.y2);
                const w = Math.abs(this.marquee.x2 - this.marquee.x1);
                const h = Math.abs(this.marquee.y2 - this.marquee.y1);
                return 'left:' + x + 'px; top:' + y + 'px; width:' + w + 'px; height:' + h + 'px;';
            },

            commitMarquee() {
                const m = this.marquee;
                if (!m) return;
                const wx1 = (Math.min(m.x1, m.x2) - this.pan.x) / this.zoom;
                const wy1 = (Math.min(m.y1, m.y2) - this.pan.y) / this.zoom;
                const wx2 = (Math.max(m.x1, m.x2) - this.pan.x) / this.zoom;
                const wy2 = (Math.max(m.y1, m.y2) - this.pan.y) / this.zoom;
                const hit = this.nodes
                    .filter(n => n.x < wx2 && n.x + n.w > wx1 && n.y < wy2 && n.y + n.h > wy1)
                    .sort((a, b) => a.id - b.id)
                    .map(n => n.id);
                this.selectedIds = m.additive
                    ? [...new Set([...this.selectedIds, ...hit])]
                    : hit;
                if (hit.length) {
                    this.selectedId = hit[0];
                    this.populatePanel(this.nodeById(hit[0]));
                } else if (!m.additive) {
                    this.selectedId = null;
                }
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
                return 'M' + x1 + ',' + y1 + ' L' + x2 + ',' + y2;
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
                    this.busy = false;
                    return true;
                } catch (e) {
                    this.error = 'Network error.';
                    this.busy = false;
                    return false;
                }
            },

            async run(method, url, body) {
                const ok = await this.post(url, method, body);
                if (ok) return await this.refresh();
                return false;
            },

            async runReload(method, url, body) {
                const ok = await this.post(url, method, body);
                if (ok) window.location.reload();
            },

            async refresh() {
                if (!this.options.mapUrl) return false;
                try {
                    const res = await fetch(this.options.mapUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => null);
                    if (!res.ok || !data || !Array.isArray(data.nodes)) return false;
                    this.applyServerNodes(data.nodes);
                    return true;
                } catch (e) {
                    return false;
                }
            },

            applyServerNodes(serverNodes) {
                const old = new Map(this.nodes.map(n => [n.id, n]));
                const saved = this.loadPositions();
                const newIds = [];

                const next = serverNodes.map(raw => {
                    const prev = old.get(raw.id);
                    const n = {
                        ...raw,
                        w: prev ? prev.w : NODE_W,
                        h: prev ? prev.h : MIN_H,
                        x: prev ? prev.x : 0,
                        y: prev ? prev.y : 0,
                        pinned: prev ? prev.pinned : false,
                    };
                    if (prev) return n;
                    if (saved && saved[raw.id]) {
                        n.x = saved[raw.id].x;
                        n.y = saved[raw.id].y;
                        n.pinned = true;
                        return n;
                    }
                    newIds.push(raw.id);
                    return n;
                });

                this.nodes = next;

                if (newIds.length) {
                    const pending = new Set(newIds);
                    newIds.forEach(id => {
                        const node = this.nodeById(id);
                        if (!node) return;
                        const intent = this.spawnIntent(node);
                        this.placeWithoutOverlap(node, intent, pending);
                        pending.delete(id);
                    });
                }

                this.selectedIds = this.selectedIds.filter(id => this.nodeById(id));
                if (this.selectedId && !this.nodeById(this.selectedId)) {
                    this.selectedId = this.selectedIds[this.selectedIds.length - 1] || null;
                    if (this.selectedId) this.populatePanel(this.nodeById(this.selectedId));
                }

                this.$nextTick(() => {
                    this.measure();
                    if (newIds.length) this.positionNewNodes(newIds);
                    this.resizeWorld();
                    if (newIds.length && newIds.some(id => this.outsideViewport(id))) this.fit();
                    this.savePositions();
                });
            },

            positionNewNodes(newIds) {
                const pending = new Set(newIds);
                newIds.forEach(id => {
                    const node = this.nodeById(id);
                    if (!node) return;
                    const intent = this.spawnIntent(node);
                    this.placeWithoutOverlap(node, intent, pending);
                    pending.delete(id);
                });
            },

            spawnIntent(node) {
                const kids = this.nodes.filter(n => n.parent_id === node.id);
                if (kids.length) {
                    const ref = kids[0];
                    return { x: ref.x + (ref.w - node.w) / 2, y: ref.y - node.h - V_GAP };
                }
                if (node.parent_id != null) {
                    const parent = this.nodeById(node.parent_id);
                    if (parent) {
                        const sibs = this.nodes.filter(n => n.parent_id === parent.id && n.id !== node.id);
                        const x = sibs.length
                            ? Math.max(...sibs.map(s => s.x + s.w)) + H_GAP
                            : parent.x + (parent.w - node.w) / 2;
                        return { x, y: parent.y + parent.h + V_GAP };
                    }
                }
                const roots = this.nodes.filter(n => n.parent_id == null && n.id !== node.id);
                const x = roots.length ? Math.max(...roots.map(r => r.x + r.w)) + ROOT_GAP : 0;
                return { x, y: 0 };
            },

            placeWithoutOverlap(node, intent, pending) {
                const startX = Math.max(0, intent.x);
                let x = startX;
                let y = Math.max(0, intent.y);
                let attempts = 0;
                while (attempts < 100 && this.collides(x, y, node, pending)) {
                    x += node.w + H_GAP;
                    attempts++;
                    if (attempts % 25 === 0) {
                        x = startX;
                        y += node.h + V_GAP;
                    }
                }
                node.x = Math.round(x);
                node.y = Math.round(y);
            },

            collides(x, y, node, pending) {
                for (const o of this.nodes) {
                    if (pending && pending.has(o.id)) continue;
                    const pad = 6;
                    if (x < o.x + o.w + pad && x + node.w + pad > o.x && y < o.y + o.h + pad && y + node.h + pad > o.y) {
                        return true;
                    }
                }
                return false;
            },

            outsideViewport(id) {
                const n = this.nodeById(id);
                if (!n) return false;
                const viewport = this.$refs.viewport;
                const vw = viewport ? viewport.clientWidth : 800;
                const vh = viewport ? viewport.clientHeight : 540;
                const sx = n.x * this.zoom + this.pan.x;
                const sy = n.y * this.zoom + this.pan.y;
                return sx > vw || sy > vh || sx + n.w * this.zoom < 0 || sy + n.h * this.zoom < 0;
            },

            addTopLevel() {
                this.run('POST', this.options.addNodeUrl, { parent_id: '', kind: this.payload.kind });
            },

            async saveRole() {
                const node = this.selected();
                if (!node || !this.typeRoleId) return;
                if (String(this.typeRoleId) === String(node.role_id)) {
                    this.error = '';
                    return;
                }
                const ok = await this.post(node.urls.role, 'PATCH', { role_id: this.typeRoleId });
                if (ok) await this.refresh();
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
