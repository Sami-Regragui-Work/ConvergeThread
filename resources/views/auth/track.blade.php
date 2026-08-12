@extends('layouts.app')
@section('title', 'Track Registration Request')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-brand-500 flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">
                    CT</div>
                <h1 class="text-2xl font-bold text-white">Track your request</h1>
                <p class="text-slate-400 text-sm mt-1">See whether your registration was accepted</p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ route('auth.track.store') }}" class="space-y-5">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email', $tracked->email ?? '') }}" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="you@example.com">
                        @error('email')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                        Check status
                    </button>
                </form>

                @isset($tracked)
                    @php
                        $status = $tracked->isExpired() ? 'expired' : $tracked->status;
                        $badge = match ($status) {
                            'approved' => ['bg-emerald-500/15 text-emerald-300', 'Accepted'],
                            'pending' => ['bg-amber-500/15 text-amber-300', 'Pending review'],
                            'rejected' => ['bg-red-500/15 text-red-300', 'Declined'],
                            'expired' => ['bg-slate-500/15 text-slate-300', 'Expired'],
                            default => ['bg-slate-500/15 text-slate-300', ucfirst($status)],
                        };
                        $note = match ($status) {
                            'approved' => 'Your request was accepted. You can now sign in with your email and password.',
                            'pending' => 'Your request is awaiting an admin\'s review. You\'ll be able to sign in once accepted.',
                            'rejected' => 'Your request was declined. Contact your workspace admin if you believe this is a mistake.',
                            'expired' => 'Your request expired before an admin accepted it. Please register again.',
                            default => 'Status: '.$status,
                        };
                    @endphp
                    <div class="mt-6 border-t border-white/5 pt-6 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-slate-500">Submitted</span>
                            <span class="text-sm text-slate-300">{{ $tracked->created_at->format('M j, Y H:i') }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-slate-500 shrink-0">Workspace</span>
                            <span class="text-sm text-slate-300 min-w-0 truncate">{{ $tracked->tenant_slug ?? 'Unassigned' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs text-slate-500">Status</span>
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg {{ $badge[0] }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                {{ $badge[1] }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-400">{{ $note }}</p>
                    </div>
                @endisset

                <p class="text-center text-sm text-slate-500 mt-6">
                    <a href="{{ url('/auth/login') }}" class="text-brand-400 hover:text-brand-300 transition">Back to sign in</a>
                </p>
            </div>
        </div>
    </div>
@endsection
