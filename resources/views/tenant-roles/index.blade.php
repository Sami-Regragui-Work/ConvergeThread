@extends('layouts.app')
@section('title', 'Tenant Roles')

@section('content')
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-xl font-bold text-white">Tenant Roles</h1>
            <a href="{{ url('/tenant-roles/create') }}"
                class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                + New Role
            </a>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($roles as $role)
                    <div class="px-5 py-4 flex items-center gap-4 hover:bg-white/5 transition group">
                        <div
                            class="w-8 h-8 rounded-lg bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-bold shrink-0">
                            {{ strtoupper(substr($role->name, 0, 1)) }}
                        </div>
                        <span class="flex-1 text-sm text-white font-medium">{{ $role->name }}</span>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                            <a href="{{ url('/tenant-roles/' . $role->id . '/edit') }}"
                                class="p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </a>
                            <form method="POST" action="{{ url('/tenant-roles/' . $role->id) }}">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('Delete role?')"
                                    class="p-2 rounded-lg hover:bg-red-500/10 text-slate-400 hover:text-red-400 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No tenant roles defined yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection