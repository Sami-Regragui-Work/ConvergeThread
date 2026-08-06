<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-dvh">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ConvergeThread') }} @hasSection('title') — @yield('title') @endif</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        surface: { 50: '#f8fafc', 100: '#1e2433', 200: '#16192a', 300: '#11141f', 400: '#0c0e18' },
                        brand: { 400: '#818cf8', 500: '#6366f1', 600: '#4f46e5' }
                    },
                    zIndex: {
                        300: '300',
                    },
                }
            }
        }
    </script>
    @if(config('broadcasting.default') === 'reverb')
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0-rc2/dist/web/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js"></script>
        <script>
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: @js(config('broadcasting.connections.reverb.key')),
                wsHost: @js(config('broadcasting.connections.reverb.options.host')),
                wsPort: @js((int) config('broadcasting.connections.reverb.options.port')),
                wssPort: @js((int) config('broadcasting.connections.reverb.options.port')),
                forceTLS: @js(config('broadcasting.connections.reverb.options.scheme') === 'https'),
                enabledTransports: ['ws', 'wss'],
                authEndpoint: @js(url('/broadcasting/auth')),
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                },
            });
        </script>
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 3px;
        }

        body {
            background-color: #0c0e18;
            color: #e2e8f0;
            font-family: 'Inter', system-ui, sans-serif;
        }

        .mention-pill {
            display: inline;
            padding: 0.05rem 0.4rem;
            margin: 0 0.05rem;
            border-radius: 0.375rem;
            font-weight: 600;
            background-color: rgba(76, 29, 149, 0.72);
            color: #ede9fe;
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
        }
    </style>
</head>

<body class="antialiased @hasSection('fill-height') h-dvh overflow-hidden @else min-h-dvh @endif"
    x-data="{
        sidebarOpen: {{ auth()->check() ? "window.matchMedia('(min-width: 1024px)').matches" : 'false' }},
        unreadNotifs: {{ auth()->check() ? auth()->user()->unreadNotifications()->count() : 0 }},
        stamps: {},
        ownerSearch: '',
        realtimeReady: false,
        init() {
            @auth
            this.setupRealtime();
            this.pollWorkspace();
            setInterval(() => this.pollWorkspace(), this.realtimeReady ? 20000 : 8000);
            @endauth
        },
        ownerMatch(haystack) {
            const q = (this.ownerSearch || '').toLowerCase().trim();
            if (!q) return true;
            return String(haystack || '').toLowerCase().includes(q);
        },
        setupRealtime() {
            if (!window.Echo) return;
            const tenantId = {{ (int) (auth()->user()->tenant_id ?? 0) }};
            if (tenantId) {
                window.Echo.private('workspace.' + tenantId)
                    .listen('.workspace.updated', (e) => this.onSyncEvent(e?.scopes || ['workspace']));
            }
            @if(auth()->check() && auth()->user()->isOwner())
            window.Echo.private('owner')
                .listen('.owner.updated', (e) => this.onSyncEvent(e?.scopes || ['workspace']));
            @endif
            this.realtimeReady = true;
        },
        onSyncEvent(scopes) {
            this.reloadIfNeeded(scopes);
            this.pollWorkspace(true);
        },
        reloadIfNeeded(scopes) {
            const el = document.querySelector('[data-sync]');
            if (!el) return;
            const needed = (el.dataset.sync || '').split(',').map(s => s.trim()).filter(Boolean);
            if (!needed.length || scopes.includes('workspace') || needed.some(s => scopes.includes(s))) {
                window.location.reload();
            }
        },
        async pollWorkspace(notificationsOnly = false) {
            try {
                const r = await fetch(@js(route('workspace.sync')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) return;
                const d = await r.json();
                if (this.unreadNotifs !== d.unread_notifications) {
                    this.unreadNotifs = d.unread_notifications;
                    const badge = document.querySelector('[data-notif-badge]');
                    if (badge) {
                        badge.classList.toggle('hidden', !d.unread_notifications);
                        badge.textContent = d.unread_notifications > 9 ? '9+' : d.unread_notifications;
                    }
                }
                if (notificationsOnly) return;

                const el = document.querySelector('[data-sync]');
                if (el) {
                    const needed = (el.dataset.sync || '').split(',').map(s => s.trim()).filter(Boolean);
                    const keys = needed.length ? needed : Object.keys(d).filter(k => !['unread_notifications', 'server_time', 'groups_updated_at', 'users_updated_at'].includes(k));
                    for (const key of keys) {
                        if (this.stamps[key] != null && d[key] != null && this.stamps[key] !== d[key]) {
                            window.location.reload();
                            return;
                        }
                    }
                }

                for (const [key, value] of Object.entries(d)) {
                    if (['unread_notifications', 'server_time'].includes(key)) continue;
                    this.stamps[key] = value;
                }
            } catch (e) {}
        }
    }">
    <div class="flex h-dvh min-h-0 bg-surface-400">
        @auth
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-40 w-64 border-r border-white/5 bg-surface-300 transition-transform duration-300 flex flex-col">

                {{-- Logo --}}
                <div class="flex items-center gap-3 px-5 py-4 border-b border-white/5 select-none">
                    <div
                        class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center text-white font-bold text-sm">
                        CT
                    </div>
                    <span class="font-semibold text-white text-sm tracking-wide">ConvergeThread</span>
                </div>

                {{-- Nav links --}}
                <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                    @if(auth()->user()->tenant_id === 1)
                        <p class="text-xs text-slate-500 uppercase tracking-widest px-2 mb-2">Owner</p>

                        <a href="{{ route('owner.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('owner*') ? 'bg-brand-500/10 text-brand-400' : '' }}">
                            Dashboard
                        </a>
                    @else
                        <p class="text-xs text-slate-500 uppercase tracking-widest px-2 mb-2">Workspace</p>

                        <a href="{{ route('groups.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('groups*') ? 'bg-brand-500/10 text-brand-400' : '' }}">
                            Groups
                        </a>

                        <a href="{{ route('merge-sessions.index') }}"
                            class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('merge-sessions*') ? 'bg-brand-500/10 text-brand-400' : '' }}">
                            Merge Sessions
                        </a>

                        @can('viewAny', App\Models\TenantRole::class)
                            <a href="{{ route('tenant-roles.index') }}"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('tenant-roles*') ? 'bg-brand-500/10 text-brand-400' : '' }}">
                                Tenant Roles
                            </a>
                        @endcan

                        @php
                            $tenantPermissions = app(\App\Services\TenantPermissionService::class);
                            $canViewWorkspaceMembers = $tenantPermissions->canViewWorkspaceMembers(auth()->user());
                        @endphp
                        @if($canViewWorkspaceMembers)
                            <a href="{{ route('workspace.members.index') }}"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('workspace/members*') ? 'bg-brand-500/10 text-brand-400' : '' }}">
                                Workspace Members
                            </a>
                        @endif
                        @can('viewAny', App\Models\TenantRole::class)
                            <a href="{{ route('hierarchies.index') }}"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('hierarchies*') ? 'bg-brand-500/10 text-brand-400' : '' }}">
                                Hierarchies
                            </a>
                        @endcan
                    @endif
                </nav>

                {{-- Sidebar footer: user identity + logout --}}
                <div class="border-t border-white/5 px-3 py-3 shrink-0">
                    <div class="flex items-center gap-3 px-2 py-2 mb-1">
                        <div
                            class="w-7 h-7 rounded-full bg-brand-500/20 text-brand-400 flex items-center justify-center text-xs font-semibold shrink-0">
                            {{ strtoupper(substr(auth()->user()->display_name ?? auth()->user()->email, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-white font-medium truncate">
                                {{ auth()->user()->displayLabel() }}
                            </p>
                            @if(auth()->user()->display_name && auth()->user()->display_name !== auth()->user()->email)
                                <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('auth.logout') }}">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:bg-white/5 hover:text-red-400 text-sm transition">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>

            </aside>

            <div x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"
                class="fixed inset-0 z-30 bg-slate-950/50 backdrop-blur-[1px] lg:hidden"></div>
        @endauth

        <div class="flex min-w-0 flex-1 flex-col min-h-0 h-dvh transition-[margin] duration-300"
            :class="sidebarOpen ? 'lg:ml-64' : 'lg:ml-0'">
            {{-- Header --}}
            <header
                class="sticky top-0 z-20 flex items-center gap-4 px-4 sm:px-5 py-3 border-b border-white/5 bg-surface-300/95 backdrop-blur shrink-0">
                @auth
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="inline-flex items-center justify-center p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition"
                        type="button" aria-label="Toggle sidebar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    @include('partials.back-button')
                @endauth

                <div class="flex items-center gap-2 min-w-0 select-none">
                    @auth
                        <h1 class="text-sm font-semibold text-white truncate">
                            @if(auth()->user()->isOwner())
                                Owner
                            @else
                                {{ auth()->user()->tenant?->name ?? 'Workspace' }}
                            @endif
                        </h1>
                    @else
                        <div
                            class="w-7 h-7 rounded-lg bg-brand-500 flex items-center justify-center text-white font-bold text-xs">
                            CT
                        </div>
                        <h1 class="text-sm font-semibold text-white truncate">ConvergeThread</h1>
                    @endauth
                </div>

                <div class="ml-auto flex items-center gap-3">
                    @auth
                        @if(auth()->user()->isOwner())
                            <div class="hidden md:block">
                                <input type="search" x-model="ownerSearch" placeholder="Search tenants, users…"
                                    class="bg-surface-200 border border-white/10 text-white text-xs rounded-lg px-3 py-1.5 w-48 focus:outline-none focus:ring-1 focus:ring-brand-500/50"
                                    @keydown.enter.prevent>
                            </div>
                        @endif
                        <a href="{{ route('notifications.index') }}"
                            class="relative inline-flex items-center justify-center p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition"
                            title="Notifications">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span data-notif-badge
                                class="{{ auth()->user()->unreadNotifications()->count() ? '' : 'hidden' }} absolute -top-0.5 -right-0.5 min-w-5 h-5 px-0.5 rounded-full bg-red-500 text-[10px] font-bold text-white flex items-center justify-center"
                                x-text="unreadNotifs > 9 ? '9+' : unreadNotifs"></span>
                        </a>
                    @endauth
                    @guest
                        @if(request()->is('auth/login'))
                            <a href="{{ route('auth.register') }}"
                                class="text-sm text-brand-400 hover:text-brand-300 transition">Register</a>
                        @else
                            <a href="{{ route('auth.login') }}"
                                class="text-sm text-brand-400 hover:text-brand-300 transition">Sign in</a>
                        @endif
                    @endguest
                </div>
            </header>

            {{-- Flash messages --}}
            @include('partials.flash')

            {{-- Main content --}}
            <main class="flex flex-1 flex-col min-h-0 @hasSection('fill-height') overflow-hidden @else overflow-y-auto px-4 py-6 sm:px-6 lg:px-8 @endif">
                <div @class([
                    'flex flex-1 min-h-0 flex-col w-full' => View::hasSection('fill-height'),
                    'w-full max-w-7xl mx-auto' => ! View::hasSection('fill-height'),
                ])>
                    @unless(View::hasSection('fill-height'))
                        @include('partials.breadcrumbs')
                        @include('partials.validation-errors')
                    @endunless
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @include('partials.confirm-dialog')

    @stack('scripts')
</body>

</html>
