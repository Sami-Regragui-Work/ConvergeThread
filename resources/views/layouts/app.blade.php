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

<body class="antialiased" x-data="{ sidebarOpen: true }">
    @include('partials.flash')

    <div class="flex h-screen overflow-hidden bg-surface-400">
        @auth
            <aside :class="sidebarOpen ? 'w-64' : 'w-0 overflow-hidden'"
                class="flex-shrink-0 transition-all duration-300 bg-surface-300 border-r border-white/5 flex flex-col">
                <div class="flex items-center gap-3 px-5 py-4 border-b border-white/5">
                    <div
                        class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center text-white font-bold text-sm">
                        CT</div>
                    <span class="font-semibold text-white text-sm tracking-wide">ConvergeThread</span>
                </div>
                <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                    <p class="text-xs text-slate-500 uppercase tracking-widest px-2 mb-2">Workspace</p>
                    <a href="{{ url('/groups') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('groups*') ? 'bg-brand-500/10 text-brand-400' : '' }}">Groups</a>
                    <a href="{{ url('/merge-sessions') }}"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-white/5 hover:text-white text-sm transition {{ request()->is('merge-sessions*') ? 'bg-brand-500/10 text-brand-400' : '' }}">Merge
                        Sessions</a>
                </nav>
            </aside>
        @endauth

        <div class="flex flex-col flex-1 overflow-hidden">
            <header class="flex items-center gap-4 px-5 py-3 border-b border-white/5 bg-surface-300 shrink-0">
                @auth
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="p-1.5 rounded-md hover:bg-white/5 text-slate-400 hover:text-white transition">Menu</button>
                @endauth

                {{-- Brand Logo in Header --}}
                <div class="flex items-center gap-2">
                    <div
                        class="w-7 h-7 rounded-lg bg-brand-500 flex items-center justify-center text-white font-bold text-xs">
                        CT</div>
                    <h1 class="text-sm font-semibold text-white">ConvergeThread</h1>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    @guest
                        @if(request()->is('auth/login'))
                            <a href="{{ url('/auth/register') }}"
                                class="text-sm text-brand-400 hover:text-brand-300 transition">Register</a>
                        @else
                            <a href="{{ url('/auth/login') }}"
                                class="text-sm text-brand-400 hover:text-brand-300 transition">Sign in</a>
                        @endif
                    @endguest
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6 flex items-center justify-center">
                <div class="w-full">
                    @include('partials.validation-errors')
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>

</html>