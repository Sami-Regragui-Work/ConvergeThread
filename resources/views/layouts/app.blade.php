<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

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
                    }
                }
            }
        }
    </script>
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
    </style>
</head>

<body class="antialiased" x-data="{ sidebarOpen: false }">
    @include('partials.flash')

    <div class="flex min-h-screen bg-surface-400">
        @auth
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed inset-y-0 left-0 z-40 w-64 border-r border-white/5 bg-surface-300 transition-transform duration-300 lg:translate-x-0 lg:static lg:inset-auto lg:z-auto flex flex-col">

                {{-- Logo --}}
                <div class="flex items-center gap-3 px-5 py-4 border-b border-white/5">
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
                                {{ auth()->user()->display_name ?? auth()->user()->email }}
                            </p>
                            <p class="text-xs text-slate-500 truncate">{{ auth()->user()->email }}</p>
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

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="sticky top-0 z-20 flex items-center gap-4 px-4 sm:px-5 py-3 border-b border-white/5 bg-surface-300/95 backdrop-blur shrink-0">
                @auth
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="inline-flex items-center justify-center p-2 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition lg:hidden"
                        type="button">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                @endauth

                <div class="flex items-center gap-2 min-w-0">
                    <div
                        class="w-7 h-7 rounded-lg bg-brand-500 flex items-center justify-center text-white font-bold text-xs">
                        CT
                    </div>
                    <h1 class="text-sm font-semibold text-white truncate">ConvergeThread</h1>
                </div>

                <div class="ml-auto flex items-center gap-3">
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

            <main class="flex-1 overflow-y-auto px-4 py-6 sm:px-6 lg:px-8">
                <div class="w-full max-w-7xl mx-auto">
                    @include('partials.validation-errors')
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
</body>

</html>