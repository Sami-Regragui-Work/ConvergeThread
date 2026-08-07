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
                        200: '200',
                        250: '250',
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
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.35);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.55);
        }

        ::-webkit-scrollbar-corner {
            background: transparent;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(99, 102, 241, 0.45) transparent;
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

        .ct-md-body p { margin: 0.35em 0; }
        .ct-md-body p:first-child { margin-top: 0; }
        .ct-md-body p:last-child { margin-bottom: 0; }
        .ct-md-body .ct-md-h,
        .ct-md-body h1, .ct-md-body h2, .ct-md-body h3,
        .ct-md-body h4, .ct-md-body h5, .ct-md-body h6 {
            font-weight: 700; margin: 0.5em 0 0.25em; line-height: 1.25;
        }
        .ct-md-body h1.ct-md-h, .ct-md-body h1 { font-size: 1.15em; }
        .ct-md-body h2.ct-md-h, .ct-md-body h2 { font-size: 1.05em; }
        .ct-md-body h3.ct-md-h, .ct-md-body h3 { font-size: 1em; }
        .ct-md-body h4, .ct-md-body h5, .ct-md-body h6 { font-size: 0.95em; }
        .ct-md-body .ct-md-list,
        .ct-md-body ul, .ct-md-body ol { margin: 0.35em 0; padding-left: 1.25em; list-style: disc; }
        .ct-md-body ol.ct-md-list, .ct-md-body ol { list-style: decimal; }
        .ct-md-body .ct-md-list .ct-md-list,
        .ct-md-body ul ul, .ct-md-body ol ol, .ct-md-body ul ol, .ct-md-body ol ul { margin: 0.2em 0; }
        .ct-md-body .ct-md-code,
        .ct-md-body :not(pre) > code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.85em;
            padding: 0.1em 0.35em;
            border-radius: 0.3rem;
            background: rgba(15, 23, 42, 0.55);
        }
        .ct-md-body .ct-md-codeblock { margin: 0.45em 0; }
        .ct-md-body .ct-md-lang {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #94a3b8;
            margin-bottom: 0.2rem;
        }
        .ct-md-body .ct-md-pre,
        .ct-md-body pre {
            margin: 0;
            padding: 0.6em 0.75em;
            border-radius: 0.5rem;
            overflow-x: auto;
            background: rgba(15, 23, 42, 0.65);
            font-size: 0.8em;
        }
        .ct-md-body pre code.hljs,
        .ct-md-body .hljs {
            background: transparent !important;
            padding: 0;
            color: inherit;
        }
        .ct-md-body pre code {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: inherit;
            background: transparent;
            padding: 0;
            border-radius: 0;
            color: #e2e8f0;
        }
        .ct-md-body .ct-md-link,
        .ct-md-body a { color: #a5b4fc; text-decoration: underline; }
        .ct-md-body .ct-md-table-wrap { overflow-x: auto; margin: 0.5em 0; }
        .ct-md-body .ct-md-table,
        .ct-md-body table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85em;
        }
        .ct-md-body .ct-md-table th,
        .ct-md-body .ct-md-table td,
        .ct-md-body table th,
        .ct-md-body table td {
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.35em 0.55em;
            text-align: left;
        }
        .ct-md-body .ct-md-table th,
        .ct-md-body table th {
            background: rgba(15, 23, 42, 0.55);
            font-weight: 600;
        }
        .ct-md-body blockquote {
            margin: 0.4em 0;
            padding: 0.15em 0.75em;
            border-left: 3px solid rgba(165, 180, 252, 0.55);
            color: #cbd5e1;
        }
        .ct-md-body hr {
            border: 0;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            margin: 0.75em 0;
        }
        .ct-md-body del, .ct-md-body s { color: #94a3b8; }
        .ct-md-body mark {
            background: rgba(250, 204, 21, 0.35);
            color: #fef9c3;
            padding: 0 0.15em;
            border-radius: 0.2rem;
        }
        .ct-md-body input[type="checkbox"] {
            margin-right: 0.4em;
            vertical-align: middle;
        }
        .ct-md-body dl { margin: 0.4em 0; }
        .ct-md-body dt { font-weight: 600; color: #e2e8f0; }
        .ct-md-body dd { margin: 0.15em 0 0.45em 1em; color: #cbd5e1; }
        .ct-md-body details {
            margin: 0.4em 0;
            padding: 0.4em 0.6em;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            background: rgba(15, 23, 42, 0.35);
        }
        .ct-md-body summary { cursor: pointer; font-weight: 600; }
        .ct-md-body img {
            max-width: 100%;
            height: auto;
            border-radius: 0.5rem;
            margin: 0.35em 0;
        }
        .ct-md-body .footnotes {
            margin-top: 0.75em;
            padding-top: 0.5em;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.8em;
            color: #94a3b8;
        }
        .ct-md-body .ct-md-spoiler {
            background: #1e293b;
            color: transparent;
            border-radius: 0.25rem;
            cursor: pointer;
            padding: 0 0.2em;
            transition: color 0.12s ease, background 0.12s ease;
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
        }
        .ct-md-body .ct-md-spoiler:not(.is-revealed):hover {
            background: #334155;
        }
        .ct-md-body .ct-md-spoiler.is-revealed {
            color: inherit;
            background: rgba(51, 65, 85, 0.55);
        }
        .ct-md-body .ct-md-alert {
            margin: 0.5em 0;
            padding: 0.55em 0.75em;
            border-radius: 0.6rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(15, 23, 42, 0.45);
        }
        .ct-md-body .ct-md-alert-note { border-color: rgba(96, 165, 250, 0.45); }
        .ct-md-body .ct-md-alert-tip { border-color: rgba(52, 211, 153, 0.45); }
        .ct-md-body .ct-md-alert-important { border-color: rgba(167, 139, 250, 0.5); }
        .ct-md-body .ct-md-alert-warning { border-color: rgba(251, 191, 36, 0.5); }
        .ct-md-body .ct-md-alert-caution { border-color: rgba(248, 113, 113, 0.5); }
        .ct-md-body kbd {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.8em;
            padding: 0.05em 0.35em;
            border-radius: 0.3rem;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(15, 23, 42, 0.55);
        }

        .msg-focus-pulse {
            animation: msg-focus-pulse 1.6s ease-out 1;
        }

        @keyframes msg-focus-pulse {
            0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.55); }
            100% { box-shadow: 0 0 0 12px rgba(99, 102, 241, 0); }
        }
    </style>
</head>

<body class="antialiased @hasSection('fill-height') h-dvh overflow-hidden @else min-h-dvh @endif"
    x-data="{
        sidebarOpen: false,
        unreadNotifs: {{ auth()->check() ? auth()->user()->unreadNotifications()->count() : 0 }},
        stamps: {},
        ownerSearch: '',
        realtimeReady: false,
        soundsMuted: false,
        init() {
            @auth
            const mq = window.matchMedia('(min-width: 1024px)');
            const desktopPref = () => {
                try {
                    const v = localStorage.getItem('ct_sidebar_open');
                    if (v === '1') return true;
                    if (v === '0') return false;
                } catch (e) {}
                return true;
            };
            this.sidebarOpen = mq.matches ? desktopPref() : false;
            mq.addEventListener('change', (e) => {
                this.sidebarOpen = e.matches ? desktopPref() : false;
            });
            try { this.soundsMuted = localStorage.getItem('ct_sounds_muted') === '1'; } catch (e) {}
            this.setupRealtime();
            this.pollWorkspace();
            setInterval(() => this.pollWorkspace(true), 4000);
            setInterval(() => this.pollWorkspace(false), 20000);
            window.addEventListener('ct-unread', (e) => {
                if (typeof e.detail?.count === 'number') this.applyUnread(e.detail.count);
            });
            @endauth
        },
        toggleSidebar() {
            this.sidebarOpen = !this.sidebarOpen;
            if (window.matchMedia('(min-width: 1024px)').matches) {
                try { localStorage.setItem('ct_sidebar_open', this.sidebarOpen ? '1' : '0'); } catch (e) {}
            }
        },
        closeSidebarMobile() {
            if (window.innerWidth < 1024) this.sidebarOpen = false;
        },
        ownerMatch(haystack) {
            const q = (this.ownerSearch || '').toLowerCase().trim();
            if (!q) return true;
            return String(haystack || '').toLowerCase().includes(q);
        },
        toggleSoundsMuted() {
            this.soundsMuted = !this.soundsMuted;
            try { localStorage.setItem('ct_sounds_muted', this.soundsMuted ? '1' : '0'); } catch (e) {}
        },
        playNotifSound() {
            if (this.soundsMuted) return;
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                const ctx = new Ctx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 740;
                gain.gain.value = 0.0001;
                osc.connect(gain);
                gain.connect(ctx.destination);
                const now = ctx.currentTime;
                gain.gain.linearRampToValueAtTime(0.06, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
                osc.start(now);
                osc.stop(now + 0.4);
                setTimeout(() => ctx.close().catch(() => {}), 500);
            } catch (e) {}
        },
        applyUnread(count) {
            const prev = this.unreadNotifs;
            this.unreadNotifs = count;
            const badge = document.querySelector('[data-notif-badge]');
            if (badge) {
                badge.classList.toggle('hidden', !count);
                badge.textContent = count > 9 ? '9+' : String(count);
            }
            if (count > prev) this.playNotifSound();
        },
        setupRealtime() {
            if (!window.Echo) return;
            const tenantId = {{ (int) (auth()->user()->tenant_id ?? 0) }};
            const userId = {{ (int) (auth()->id() ?? 0) }};
            if (tenantId) {
                window.Echo.private('workspace.' + tenantId)
                    .listen('.workspace.updated', (e) => this.onSyncEvent(e?.scopes || ['workspace']));
            }
            if (userId) {
                window.Echo.private('user.' + userId)
                    .listen('.notifications.unread', (e) => {
                        if (typeof e?.count === 'number') this.applyUnread(e.count);
                    });
            }
            @if(auth()->check() && auth()->user()->isOwner())
            window.Echo.private('owner')
                .listen('.owner.updated', (e) => this.onSyncEvent(e?.scopes || ['workspace']));
            @endif
            this.realtimeReady = true;
        },
        onSyncEvent(scopes) {
            if ((scopes || []).includes('notifications') || (scopes || []).includes('workspace')) {
                this.pollWorkspace(true);
            }
            this.reloadIfNeeded(scopes);
        },
        reloadIfNeeded(scopes) {
            if ((scopes || []).length === 1 && scopes[0] === 'notifications') return;
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
                if (typeof d.unread_notifications === 'number') {
                    this.applyUnread(d.unread_notifications);
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
                class="fixed inset-y-0 left-0 z-40 w-[min(100vw-3rem,16rem)] sm:w-64 max-w-full border-r border-white/5 bg-surface-300 transition-transform duration-300 flex flex-col pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)]">

                {{-- Logo --}}
                <div class="flex items-center gap-3 px-4 sm:px-5 py-3 sm:py-4 border-b border-white/5 select-none">
                    <div
                        class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center text-white font-bold text-sm shrink-0">
                        CT
                    </div>
                    <span class="font-semibold text-white text-sm tracking-wide truncate">ConvergeThread</span>
                    <button type="button" @click="sidebarOpen = false"
                        class="ml-auto p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 lg:hidden"
                        aria-label="Close sidebar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Nav links --}}
                <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1" @click="closeSidebarMobile()">
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
                class="sticky top-0 z-20 flex items-center gap-2 sm:gap-4 px-3 sm:px-5 py-3 border-b border-white/5 bg-surface-300/95 backdrop-blur shrink-0">
                @auth
                    <button @click="toggleSidebar()"
                        class="inline-flex items-center justify-center p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition"
                        type="button" aria-label="Toggle sidebar" :aria-expanded="sidebarOpen">
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

                <div class="ml-auto flex items-center gap-1.5 sm:gap-3">
                    @auth
                        @php
                            $showChatBrowse = !auth()->user()->isOwner();
                        @endphp
                        @if(auth()->user()->isOwner())
                            <div class="hidden md:block">
                                <input type="search" x-model="ownerSearch" placeholder="Search tenants, users…"
                                    class="bg-surface-200 border border-white/10 text-white text-xs rounded-lg px-3 py-1.5 w-48 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500/50 transition"
                                    @keydown.enter.prevent>
                            </div>
                        @elseif($showChatBrowse)
                            <button type="button" @click="window.__openChatSearch && window.__openChatSearch()"
                                class="inline-flex items-center gap-1.5 p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition"
                                title="Search chats">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <span class="hidden sm:inline text-xs">Search</span>
                            </button>
                            <button type="button" @click="window.__openChatMedia && window.__openChatMedia()"
                                class="inline-flex items-center gap-1.5 p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition"
                                title="Files by thread">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                <span class="hidden sm:inline text-xs">Files</span>
                            </button>
                        @endif
                        <button type="button" @click="toggleSoundsMuted()"
                            class="inline-flex items-center justify-center p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition"
                            :title="soundsMuted ? 'Unmute notification sounds' : 'Mute notification sounds'"
                            :class="soundsMuted ? 'text-amber-400' : ''">
                            <svg x-show="!soundsMuted" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15.536 8.464a5 5 0 010 7.072M17.95 6.05a8 8 0 010 11.9M6 10v4h3l4 4V6l-4 4H6z" />
                            </svg>
                            <svg x-show="soundsMuted" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15zM17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                            </svg>
                        </button>
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
    @auth
        @unless(auth()->user()->isOwner())
            @include('partials.chat-crypto')
            @include('partials.group-name-modal')
            @include('partials.global-call-ring')
            @include('partials.chat-search-index')
            @include('partials.chat-browse-ui')
            @include('partials.ct-markdown-script')
            @include('partials.ct-code-suggest')
            @include('partials.ct-monaco-fence')
            @include('partials.ct-media-export-script')
            @include('partials.ct-media-player-script')
        @endunless
    @endauth

    @stack('scripts')
</body>

</html>
