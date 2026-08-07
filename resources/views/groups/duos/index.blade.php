@extends('layouts.app')
@section('title', 'Duos — ' . $group->name)

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <h1 class="text-xl font-bold text-white">Duos</h1>
                <span class="text-xs text-slate-500 truncate">{{ $group->name }}</span>
            </div>
            @can('create', [App\Models\Duo::class, $group])
                <button type="button" onclick="window.__openDuoCreate && window.__openDuoCreate()"
                    class="inline-flex items-center gap-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold px-4 py-2 rounded-xl transition shrink-0">
                    + New duo
                </button>
            @endcan
        </div>

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
                                <button type="button" @click="$dispatch('confirm-action', { message: 'Delete this duo?', form: $el.closest('form') })"
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

    @include('partials.duo-create-modal')
@endsection
