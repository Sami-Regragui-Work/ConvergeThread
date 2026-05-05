<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Forbidden</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
</head>

<body class="bg-[#0c0e18] text-slate-200 antialiased min-h-screen flex items-center justify-center">
    <div class="text-center px-6">
        <div class="text-8xl font-black text-white/5 mb-6 select-none">403</div>
        <h1 class="text-2xl font-bold text-white mb-3">Access Denied</h1>
        <p class="text-slate-400 text-sm mb-8 max-w-sm mx-auto">You don't have permission to access this resource.</p>
        <a href="{{ url('/') }}"
            class="inline-block bg-indigo-500 hover:bg-indigo-600 text-white font-semibold px-6 py-2.5 rounded-xl text-sm transition">
            Go Home
        </a>
    </div>
</body>

</html>