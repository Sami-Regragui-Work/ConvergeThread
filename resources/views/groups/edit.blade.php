@extends('layouts.app')
@section('title', 'Edit Group')

@section('content')
    <div class="max-w-lg mx-auto">
        <h1 class="text-xl font-bold text-white mb-6">Edit Group</h1>

        <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
            <form method="POST" action="{{ route('groups.update', $group) }}" class="space-y-5">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Group Name</label>
                    <input type="text" name="name" value="{{ old('name', $group->name) }}" required
                        class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500">
                    @error('name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div x-data="{ autoColor: @js(empty($group->accent_color)), color: @js($group->accentColor()) }">
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Accent color</label>
                    <input type="hidden" name="accent_color" :value="autoColor ? '' : color">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                            <input type="checkbox" x-model="autoColor"
                                class="rounded border-white/20 bg-surface-400 text-brand-500 focus:ring-brand-500/50">
                            Automatic
                        </label>
                        <template x-if="!autoColor">
                            <div class="flex items-center gap-2">
                                <input type="color" x-model="color" :disabled="autoColor"
                                    class="h-8 w-12 rounded-lg border border-white/10 bg-surface-200 cursor-pointer">
                                <input type="text" x-model="color" :disabled="autoColor" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$"
                                    class="w-28 bg-surface-300 border border-white/10 text-white rounded-xl px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition"
                                    placeholder="#6366f1">
                            </div>
                        </template>
                    </div>
                    <p class="text-xs text-slate-500 mt-1.5">Automatic picks a color from the group</p>
                    @error('accent_color')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ url()->previous() }}"
                        class="flex-1 text-center bg-white/5 hover:bg-white/10 text-slate-300 font-semibold py-2.5 rounded-xl text-sm transition">
                        Cancel
                    </a>
                    <button type="submit"
                        class="flex-1 bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
