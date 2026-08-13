@extends('layouts.app')
@section('title', 'Workspace Settings')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6" data-sync="workspace,members,users">
        <div>
            <h1 class="text-xl font-bold text-white">Workspace Settings</h1>
            <p class="text-sm text-slate-500 mt-1">
                Adjust how your workspace looks to everyone inside it.
            </p>
        </div>

        @if(session('status'))
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-xl px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-white/5 flex items-center gap-2">
                <h2 class="text-sm font-semibold text-white">Workspace title</h2>
                @include('partials.help-icon', ['hint' => 'This is the name shown to your members, on the owner dashboard, and in notifications. Changing it does not change your workspace link.', 'position' => 'bottom'])
                <p class="text-xs text-slate-500 mt-0.5 ml-2">
                    The name shown to members, on the owner dashboard, and in notifications.
                </p>
            </div>
            <form method="POST" action="{{ route('workspace.settings.name') }}" class="px-5 py-4 space-y-3">
                @csrf @method('PATCH')
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required maxlength="255"
                        class="flex-1 bg-surface-300 border border-white/10 text-white text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                    <button type="submit"
                        class="text-sm text-emerald-400 hover:text-emerald-300 border border-emerald-500/30 bg-emerald-500/10 rounded-xl px-4 py-2.5 transition">
                        Save title
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-surface-200 border border-white/5 rounded-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-white/5 flex items-center gap-2">
                <h2 class="text-sm font-semibold text-white">Workspace link (slug)</h2>
                @include('partials.help-icon', ['hint' => 'Used in your workspace address and in new registrations. Lowercase letters, numbers, and underscores only.', 'position' => 'bottom'])
                <p class="text-xs text-slate-500 mt-0.5 ml-2">
                    Used in your workspace address and in new registrations. Lowercase letters, numbers, and underscores.
                </p>
            </div>
            <form method="POST" action="{{ route('workspace.settings.slug') }}" class="px-5 py-4 space-y-3">
                @csrf @method('PATCH')
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="slug" value="{{ old('slug', $tenant->slug) }}" required maxlength="255"
                        class="flex-1 bg-surface-300 border border-white/10 text-white text-sm rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition">
                    <button type="submit"
                        class="text-sm text-brand-400 hover:text-brand-300 border border-brand-500/30 bg-brand-500/10 rounded-xl px-4 py-2.5 transition">
                        Update link
                    </button>
                </div>
                <p class="text-xs text-slate-500">Tip: your slug defaults to <code class="text-slate-400">connect_threads</code>, the seed workspace.</p>
            </form>
        </div>
    </div>
@endsection
