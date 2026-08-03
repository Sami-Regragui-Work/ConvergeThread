@extends('layouts.app')
@section('title', 'Duos — ' . $group->name)

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-white">Duos</h1>
            <span class="text-xs text-slate-500">{{ $group->name }}</span>
        </div>

        @can('create', [App\Models\Duo::class, $group])
            <div class="bg-surface-200 border border-white/5 rounded-2xl px-6 py-5">
                <h2 class="text-sm font-semibold text-white mb-4">Create duo</h2>
                <form method="POST" action="{{ route('groups.duos.store', $group) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-slate-300 mb-1.5">Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm"
                            placeholder="e.g. Design sync">
                        @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-slate-300 mb-1.5">User 1</label>
                            <select name="user1_id" required class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm">
                                <option value="">— Select —</option>
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}">{{ $member->display_name ?? $member->email }}</option>
                                @endforeach
                            </select>
                            @error('user1_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm text-slate-300 mb-1.5">User 2</label>
                            <select name="user2_id" required class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm">
                                <option value="">— Select —</option>
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}">{{ $member->display_name ?? $member->email }}</option>
                                @endforeach
                            </select>
                            @error('user2_id')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <button type="submit"
                        class="bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition">
                        Create duo
                    </button>
                </form>
            </div>
        @endcan

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="divide-y divide-white/5">
                @forelse($duos as $duo)
                    <div class="flex items-center gap-4 px-5 py-4 hover:bg-white/5 transition group">
                        <a href="{{ route('messages.index', ['duo', $duo->id]) }}" class="flex items-center gap-4 flex-1 min-w-0">
                            <div class="flex -space-x-2 shrink-0">
                                @foreach([$duo->user1, $duo->user2] as $u)
                                    <div
                                        class="w-8 h-8 rounded-full bg-brand-500/10 border-2 border-surface-200 text-brand-400 flex items-center justify-center text-xs font-semibold">
                                        {{ strtoupper(substr($u->display_name ?? $u->email, 0, 1)) }}
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white font-medium">{{ $duo->name }}</p>
                                <p class="text-xs text-slate-500 truncate">
                                    {{ $duo->user1->display_name ?? $duo->user1->email }}
                                    &amp; {{ $duo->user2->display_name ?? $duo->user2->email }}
                                </p>
                            </div>
                        </a>
                        @can('delete', [$group, $duo])
                            <form method="POST" action="{{ route('groups.duos.destroy', [$group, $duo]) }}"
                                class="opacity-0 group-hover:opacity-100 transition">
                                @csrf @method('DELETE')
                                <button type="submit" onclick="return confirm('Delete this duo?')"
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
                    <div class="px-5 py-10 text-center text-slate-500 text-sm">No duos in this group yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
