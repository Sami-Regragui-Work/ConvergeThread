@extends('layouts.app')
@section('title', 'Role Overrides — ' . $group->name)

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-white">Role Overrides</h1>
            <span class="text-xs text-slate-500">{{ $group->name }}</span>
        </div>

        @can('create', [App\Models\GroupRoleOverride::class, $group])
            <div class="bg-surface-200 border border-white/5 rounded-2xl px-6 py-5">
                <h2 class="text-sm font-semibold text-white mb-4">Create override</h2>
                <form method="POST" action="{{ route('groups.role-overrides.store', $group) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-slate-300 mb-1.5">Base tenant role</label>
                        <select name="tenant_role_id" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm">
                            <option value="">— Select role —</option>
                            @foreach($tenantRoles as $role)
                                <option value="{{ $role->id }}" {{ old('tenant_role_id') == $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}</option>
                            @endforeach
                        </select>
                        @error('tenant_role_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <p class="text-xs text-slate-500">Optional: pick specific permissions below to override the base role for this group.</p>
                    @include('partials.permission-checkboxes', ['selected' => old('permissions', [])])
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                        Add override
                    </button>
                </form>
            </div>
        @endcan

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($overrides as $override)
                    <div class="px-5 py-4 flex items-center gap-4 hover:bg-white/5 transition group">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white font-medium">{{ $override->tenantRole->name ?? '—' }}</p>
                            <p class="text-xs text-slate-500">{{ count($override->permissions ?? []) }} custom permissions</p>
                        </div>
                        @can('delete', [$group, $override])
                            <form method="POST" action="{{ route('groups.role-overrides.destroy', [$group, $override]) }}"
                                class="opacity-0 group-hover:opacity-100 transition">
                                @csrf @method('DELETE')
                                <button type="button" @click="$dispatch('confirm-action', { message: 'Delete this override?', form: $el.closest('form') })"
                                    class="p-2 rounded-lg hover:bg-red-500/10 text-slate-400 hover:text-red-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No role overrides yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
