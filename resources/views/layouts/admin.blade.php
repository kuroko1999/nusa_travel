<!doctype html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') · Panel Admin {{ \App\Models\Setting::find('company_name')?->value ?? 'Nusa Travel' }}</title>
    @if(\App\Models\Setting::find('favicon')?->value)
        <link rel="icon" href="{{ Storage::url(\App\Models\Setting::find('favicon')->value) }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">
@php
    $urlSaatIni = request()->url();
    $pembayaranMenunggu = \App\Models\Payment::where('status', 'pending')->where('method', 'transfer')->count();
    $namaSitus = \App\Models\Setting::find('company_name')?->value ?? 'Nusa Travel';
@endphp

<div class="lg:grid lg:min-h-screen lg:grid-cols-[264px_1fr]">

    {{-- Sidebar admin: hanya navigasi internal, tanpa menu publik --}}
    <aside class="hidden bg-slate-950 lg:flex lg:flex-col lg:sticky lg:top-0 lg:h-screen">
        <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-500 font-black text-slate-950">N</span>
            <div class="min-w-0">
                <p class="truncate text-sm font-black text-white">{{ $namaSitus }}</p>
                <p class="text-xs font-bold tracking-[0.14em] text-teal-400 uppercase">Panel Admin</p>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto px-3 py-5">
            @include('layouts.partials.admin-nav')
        </div>

        <div class="border-t border-white/10 p-3">
            <div class="rounded-2xl bg-white/5 p-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-teal-500 text-sm font-black text-slate-950">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <div class="mt-3 grid gap-1 text-sm">
                    <a class="rounded-lg px-2 py-1.5 font-medium text-slate-300 hover:bg-white/10 hover:text-white" href="{{ route('profile.edit') }}">Profil &amp; Kata Sandi</a>
                    <a class="rounded-lg px-2 py-1.5 font-medium text-slate-300 hover:bg-white/10 hover:text-white" href="{{ route('home') }}" target="_blank" rel="noopener">Lihat situs ↗</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full rounded-lg px-2 py-1.5 text-left font-medium text-slate-300 hover:bg-red-500/20 hover:text-red-200">Keluar</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <div class="flex min-w-0 flex-col">

        {{-- Topbar ringkas: pengganti header publik di dalam admin --}}
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                <div class="flex min-w-0 items-center gap-3">
                    <a class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600 font-black text-white lg:hidden" href="{{ route('admin.dashboard') }}">N</a>
                    <div class="min-w-0">
                        <p class="text-xs font-bold tracking-[0.14em] text-slate-500 uppercase">Panel Admin</p>
                        <p class="truncate text-sm font-bold text-slate-900">@yield('title', 'Dashboard')</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a class="hidden rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:border-slate-400 sm:inline-block" href="{{ route('home') }}" target="_blank" rel="noopener">Lihat situs</a>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-sm font-black text-white lg:hidden">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                </div>
            </div>

            {{-- Navigasi mobile --}}
            <details class="border-t border-slate-200 lg:hidden">
                <summary class="cursor-pointer px-5 py-3 text-sm font-bold text-slate-700">Navigasi admin</summary>
                <div class="max-h-[70vh] overflow-y-auto bg-slate-950 px-3 py-4">
                    @include('layouts.partials.admin-nav')
                    <div class="mt-4 grid gap-1 border-t border-white/10 pt-3 text-sm">
                        <a class="rounded-lg px-2 py-1.5 font-medium text-slate-300" href="{{ route('profile.edit') }}">Profil &amp; Kata Sandi</a>
                        <a class="rounded-lg px-2 py-1.5 font-medium text-slate-300" href="{{ route('home') }}" target="_blank" rel="noopener">Lihat situs ↗</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full rounded-lg px-2 py-1.5 text-left font-medium text-red-300">Keluar</button>
                        </form>
                    </div>
                </div>
            </details>
        </header>

        <main class="min-w-0 flex-1 px-5 py-7 lg:px-8">
            @if(session('status'))
                <div class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-5 py-3 text-sm font-medium text-teal-900">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-3 text-sm text-red-900">
                    <ul class="list-disc pl-5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('admin-content')
        </main>
    </div>
</div>
</body>
</html>
