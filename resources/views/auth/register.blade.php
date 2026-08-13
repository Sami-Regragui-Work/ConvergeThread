@extends('layouts.app')
@section('title', 'Register')

@section('content')
    <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-brand-500 flex items-center justify-center text-white font-bold text-lg mx-auto mb-4">
                    CT</div>
                <h1 class="text-2xl font-bold text-white">Create account</h1>
                <p class="text-slate-400 text-sm mt-1">Request to join your workspace</p>
            </div>

            <div class="bg-surface-200 border border-white/5 rounded-2xl p-8 shadow-xl">
                <form method="POST" action="{{ url('/auth/register') }}" class="space-y-5"
                    x-data="{
                        slug: @js(old('tenant_slug') ?? ''),
                        checked: null,
                        checking: false,
                        async checkSlug() {
                            const value = this.slug.trim().toLowerCase();
                            if (!value) { this.checked = null; return; }
                            this.checking = true;
                            try {
                                const res = await fetch('/auth/check-slug/' + encodeURIComponent(value), { headers: { 'Accept': 'application/json' } });
                                this.checked = await res.json();
                            } catch (e) {
                                this.checked = null;
                            } finally {
                                this.checking = false;
                            }
                        }
                    }"
                    @input="if ($event.target.name === 'tenant_slug') { this.slug = $event.target.value; this.checked = null; }">
                    @csrf

                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <label class="block text-sm font-medium text-slate-300">Workspace Slug</label>
                            @include('partials.help-icon', ['hint' => 'Your workspace is identified by a slug. Type it to check whether it already exists. You can register without an invitation — approval is needed before you can sign in.'])
                        </div>
                        <div class="flex gap-2">
                            <input type="text" name="tenant_slug" value="{{ old('tenant_slug') }}" x-model="slug"
                                class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                                placeholder="my-workspace">
                            <button type="button" @click="checkSlug()" :disabled="checking || !slug.trim()"
                                class="shrink-0 px-4 py-2.5 rounded-xl border border-white/10 text-xs font-semibold text-brand-300 hover:bg-white/5 transition disabled:opacity-50">
                                <span x-text="checking ? 'Checking…' : 'Check slug'"></span>
                            </button>
                        </div>

                        <div x-show="checked" x-cloak class="mt-1.5 text-xs" x-transition.opacity>
                            <p x-show="checked.exists" class="text-emerald-400">
                                Workspace exists — you'll request to join
                                <span class="font-semibold" x-text="checked.name || slug"></span>.
                            </p>
                            <p x-show="!checked.exists" class="text-amber-400">
                                This workspace doesn't exist yet — the owner will create it once you're approved.
                            </p>
                        </div>

                        <p class="mt-1 text-xs text-slate-500">No invitation yet? Enter the workspace slug anyway. An admin (or the owner) reviews your request before you can sign in.</p>
                        @error('tenant_slug')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <label class="block text-sm font-medium text-slate-300">Workspace Name <span class="text-slate-500">(only for new workspaces)</span></label>
                            @include('partials.help-icon', ['hint' => 'When the slug points to a brand-new workspace, this is the title the owner will use when they create it.'])
                        </div>
                        <input type="text" name="tenant_name" value="{{ old('tenant_name') }}"
                            :disabled="checked !== null && checked.exists"
                            :placeholder="checked && checked.exists ? (checked.name || '') : 'My team workspace'"
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500 disabled:opacity-50 disabled:text-slate-500"
                            :value="checked && checked.exists ? (checked.name || '') : (checked && !checked.exists ? @js(old('tenant_name') ?? '') : @js(old('tenant_name') ?? ''))">
                        <p class="mt-1 text-xs text-slate-500">When the slug points to a new workspace, this is the title the owner will use when creating it.</p>
                        @error('tenant_name')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Display Name <span
                                class="text-slate-500">(optional)</span></label>
                        <input type="text" name="display_name" value="{{ old('display_name') }}"
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="Jane Doe">
                        @error('display_name')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="you@example.com">
                        @error('email')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                        <input type="password" name="password" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="••••••••">
                        @error('password')<p class="mt-1 text-xs text-red-400 break-words">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1.5">Confirm Password</label>
                        <input type="password" name="password_confirmation" required
                            class="w-full bg-surface-300 border border-white/10 text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition placeholder-slate-500"
                            placeholder="••••••••">
                    </div>

                    <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                        Create Account
                    </button>
                </form>

                <p class="text-center text-sm text-slate-500 mt-6">
                    Already have an account?
                    <a href="{{ url('/auth/login') }}" class="text-brand-400 hover:text-brand-300 transition">Sign in</a>
                </p>
            </div>
        </div>
    </div>
@endsection