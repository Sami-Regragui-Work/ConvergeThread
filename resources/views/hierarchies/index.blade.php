@extends('layouts.app')
@section('title', 'Hierarchies')

@section('content')
    <div class="max-w-6xl mx-auto space-y-6" data-sync="hierarchies,members">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-white">Hierarchies</h1>
                <p class="text-sm text-slate-500 mt-1">Interactive node trees for who manages whom. Members may appear in several unrelated branches, but never twice on the same root→leaf path.</p>
            </div>
            <button type="button" @click="window.__openHierarchyCreate?.()"
                class="bg-brand-500 hover:bg-brand-600 text-white px-4 py-2 rounded-xl text-sm font-semibold self-start sm:self-auto">
                + New hierarchy
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6" x-data="{ tab: 'member' }">
            <div class="lg:col-span-3 space-y-6">
                <div class="bg-surface-200 border border-white/5 rounded-2xl p-2 flex gap-1">
                    <button type="button" @click="tab = 'member'"
                        :class="tab === 'member' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'"
                        class="flex-1 text-xs font-semibold px-3 py-2 rounded-xl transition">Members</button>
                    <button type="button" @click="tab = 'role'"
                        :class="tab === 'role' ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white'"
                        class="flex-1 text-xs font-semibold px-3 py-2 rounded-xl transition">Roles</button>
                </div>
                <div class="bg-surface-200 border border-white/5 rounded-2xl p-4 max-h-80 overflow-y-auto">
                    <h2 class="text-sm font-semibold text-white mb-3">Workspace members</h2>
                    <div class="space-y-1.5">
                        @foreach($members as $member)
                            <div class="text-xs text-slate-400 px-1 py-0.5 truncate">{{ $member->displayLabel() }}</div>
                        @endforeach
                    </div>
                </div>
                <div class="bg-surface-200 border border-white/5 rounded-2xl p-4">
                    <h2 class="text-sm font-semibold text-white mb-2">How it works</h2>
                    <ul class="text-xs text-slate-500 space-y-1.5 list-disc pl-4">
                        <li>Level 0 nodes are top-level; link one under another and it becomes Level 1.</li>
                        <li>Select a node to add a child, add a parent (new level or existing), change its type, or manage its members.</li>
                        <li>Linking runs checks first: a node is never allowed to become its own ancestor.</li>
                        <li>Same member twice on one root→leaf path is rejected.</li>
                        <li>Remove a node and its children become top-level again.</li>
                        <li>Drag nodes anywhere and zoom the canvas — layout is remembered per browser.</li>
                    </ul>
                </div>
            </div>

            <div class="lg:col-span-9 space-y-4">
                {{-- Member hierarchies --}}
                <div x-show="tab === 'member'" class="space-y-4">
                    @forelse($memberHierarchies as $hierarchy)
                        @include('partials.hierarchy-map', [
                            'hierarchy' => $hierarchy,
                            'members' => $members,
                            'groups' => $groups,
                            'tenantRoles' => $tenantRoles,
                        ])
                    @empty
                        <div class="bg-surface-200 border border-white/5 rounded-2xl p-10 text-center text-slate-500 text-sm">
                            No member hierarchies yet. Create one to define who manages whom.
                        </div>
                    @endforelse
                </div>

                {{-- Role hierarchies --}}
                <div x-show="tab === 'role'" x-cloak class="space-y-4">
                    @forelse($roleHierarchies as $hierarchy)
                        @include('partials.hierarchy-map', [
                            'hierarchy' => $hierarchy,
                            'members' => $members,
                            'groups' => $groups,
                            'tenantRoles' => $tenantRoles,
                        ])
                    @empty
                        <div class="bg-surface-200 border border-white/5 rounded-2xl p-10 text-center text-slate-500 text-sm">
                            No role hierarchies yet. These map tenant roles into the same node tree so role conflicts are caught.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @include('partials.hierarchy-create-modal')
@endsection
