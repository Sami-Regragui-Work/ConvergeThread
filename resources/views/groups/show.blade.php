@extends('layouts.app')
@section('title', $group->name)

@section('content')
    @php $memberCount = $group->activeMembers->count(); @endphp

    <div class="max-w-5xl mx-auto space-y-6">

        {{-- Header --}}
        <div
            class="bg-surface-200 border border-white/5 rounded-2xl px-6 py-5 flex flex-col sm:flex-row sm:items-center gap-4">
            <div class="flex items-center gap-4 flex-1">
                <div
                    class="w-12 h-12 rounded-xl bg-brand-500/10 text-brand-400 flex items-center justify-center font-bold text-base shrink-0">
                    {{ strtoupper(substr($group->name, 0, 2)) }}
                </div>
                <div>
                    <h1 class="text-lg font-bold text-white">{{ $group->name }}</h1>
                    <p class="text-slate-500 text-xs">{{ $memberCount }} members</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @can('join', [App\Models\GroupMember::class, $group])
                    <form method="POST" action="{{ route('groups.members.join', $group) }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 bg-brand-500/10 hover:bg-brand-500/20 text-brand-400 text-sm px-4 py-2 rounded-xl transition">
                            Join
                        </button>
                    </form>
                @endcan
                @can('update', $group)
                    <a href="{{ route('groups.edit', $group) }}"
                        class="inline-flex items-center gap-2 bg-white/5 hover:bg-white/10 text-slate-300 text-sm px-4 py-2 rounded-xl transition">
                        Edit
                    </a>
                @endcan
                @can('delete', $group)
                    <form method="POST" action="{{ route('groups.destroy', $group) }}">
                        @csrf @method('DELETE')
                        <button type="submit" onclick="return confirm('Delete this group?')"
                            class="inline-flex items-center gap-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-sm px-4 py-2 rounded-xl transition">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Members --}}
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white">Members</h2>
                    @can('create', [App\Models\GroupMember::class, $group])
                        <a href="{{ route('groups.members.index', $group) }}"
                            class="text-xs text-brand-400 hover:text-brand-300 transition">+ Add</a>
                    @endcan
                </div>
                <div class="divide-y divide-white/5">
                    @forelse($group->activeMembers as $member)
                        <div class="px-5 py-3 flex items-center gap-3">
                            <div
                                class="w-7 h-7 rounded-full bg-brand-500/10 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0">
                                {{ strtoupper(substr($member->display_name ?? $member->email, 0, 1)) }}
                            </div>
                            <span class="text-sm text-slate-300 truncate">{{ $member->display_name ?? $member->email }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">No members yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Duos --}}
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white">Duos</h2>
                    <a href="{{ route('groups.duos.index', $group) }}"
                        class="text-xs text-brand-400 hover:text-brand-300 transition">View all</a>
                </div>
                <div class="divide-y divide-white/5">
                    @forelse($group->duos ?? [] as $duo)
                        <a href="{{ route('groups.duos.index', $group) }}" class="block px-5 py-3 hover:bg-white/5 transition">
                            <p class="text-sm text-slate-300">{{ $duo->name }}</p>
                        </a>
                    @empty
                        <p class="px-5 py-4 text-sm text-slate-500">No duos yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Group Chat --}}
        @can('viewAny', [App\Models\Message::class, $group])
            <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white">Group Chat</h2>
                    @if($memberCount >= 3)
                        <a href="{{ route('messages.index', ['group', $group->id]) }}"
                            class="text-xs text-brand-400 hover:text-brand-300 transition">Open chat</a>
                    @endif
                </div>
                @if($memberCount < 3)
                    <div class="px-5 py-6 flex flex-col items-center gap-2 text-center">
                        <p class="text-sm text-slate-500">
                            Group chat is available once there are at least 3 members.
                        </p>
                        <p class="text-xs text-slate-600">
                            {{ 3 - $memberCount }} more {{ Str::plural('member', 3 - $memberCount) }} needed.
                        </p>
                    </div>
                @else
                    <div class="px-5 py-4">
                        <a href="{{ route('messages.index', ['group', $group->id]) }}"
                            class="inline-flex items-center gap-2 bg-brand-500/10 hover:bg-brand-500/20 text-brand-400 text-sm px-4 py-2 rounded-xl transition">
                            Go to chat
                        </a>
                    </div>
                @endif
            </div>
        @endcan

    </div>
@endsection
