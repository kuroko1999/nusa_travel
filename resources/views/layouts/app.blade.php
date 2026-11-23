<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d9488">
    <title>@yield('title', 'Nusa Travel') · {{ \App\Models\Setting::find('company_name')?->value ?? 'Nusa Travel' }}</title>
    @if(\App\Models\Setting::find('favicon')?->value)
        <link rel="icon" href="{{ Storage::url(\App\Models\Setting::find('favicon')->value) }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f5f6f4] text-slate-900 antialiased">
<header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-5 py-3.5">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 font-black text-lg tracking-tight text-slate-900">
            @if(\App\Models\Setting::find('logo')?->value)
                <img class="h-9 w-9 rounded-xl object-contain" src="{{ Storage::url(\App\Models\Setting::find('logo')->value) }}" alt="Logo">
            @else
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-600 text-white">N</span>
            @endif
            <span>{{ \App\Models\Setting::find('company_name')?->value ?? 'Nusa Travel' }}</span>
        </a>

        <nav class="hidden items-center gap-7 text-sm font-semibold text-slate-600 lg:flex">
            <a href="{{ route('home') }}#rute" class="transition hover:text-teal-700">Rute</a>
            <a href="{{ route('home') }}#jadwal" class="transition hover:text-teal-700">Jadwal</a>
            <a href="{{ route('home') }}#armada" class="transition hover:text-teal-700">Armada</a>
            <a href="{{ route('home') }}#cara" class="transition hover:text-teal-700">Cara Pemesanan</a>
            <a href="{{ route('home') }}#cek" class="transition hover:text-teal-700">Cek Booking</a>
        </nav>

        <div class="flex items-center gap-2.5 text-sm font-semibold">
            @auth
                @if(auth()->user()->role === 'super_admin')
                    <a class="btn-outline hidden sm:inline-flex" href="{{ route('admin.dashboard') }}">Panel Admin</a>
                @else
                    <a class="link hidden sm:inline" href="{{ route('customer.dashboard') }}">Pesanan Saya</a>
                @endif
                <a class="link hidden md:inline" href="{{ route('profile.edit') }}">Profil</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn-outline">Keluar</button>
                </form>
            @else
                <a class="link" href="{{ route('login') }}">Masuk</a>
                <a class="btn-primary" href="{{ route('register') }}">Daftar</a>
            @endauth
        </div>
    </div>

    <details class="mx-auto max-w-7xl px-5 pb-3 lg:hidden">
        <summary class="cursor-pointer text-sm font-bold text-teal-700">Menu</summary>
        <nav class="mt-3 grid gap-2 text-sm font-semibold">
            <a href="{{ route('home') }}#rute">Rute</a>
            <a href="{{ route('home') }}#jadwal">Jadwal Keberangkatan</a>
            <a href="{{ route('home') }}#armada">Armada Kami</a>
            <a href="{{ route('home') }}#cara">Cara Pemesanan</a>
            <a href="{{ route('home') }}#cek">Cek Booking</a>
            @auth
                @if(auth()->user()->role === 'super_admin')
                    <a href="{{ route('admin.dashboard') }}">Panel Admin</a>
                @else
                    <a href="{{ route('customer.dashboard') }}">Pesanan Saya</a>
                    <a href="{{ route('customer.documents', 'tiket') }}">Tiket Saya</a>
                    <a href="{{ route('customer.documents', 'faktur') }}">Faktur Saya</a>
                @endif
                <a href="{{ route('profile.edit') }}">Profil</a>
            @endauth
        </nav>
    </details>
</header>

@if(session('status'))
    <div class="mx-auto max-w-7xl px-5 pt-4">
        <div class="rounded-2xl border border-teal-200 bg-teal-50 px-5 py-3 text-sm font-medium text-teal-900">{{ session('status') }}</div>
    </div>
@endif
@if($errors->any())
    <div class="mx-auto max-w-7xl px-5 pt-4">
        <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-3 text-sm text-red-900">
            <ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    </div>
@endif

<main>@yield('content')</main>

<footer class="mt-4 bg-slate-950 text-slate-300">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-14 md:grid-cols-[1.3fr_0.8fr_1fr]">
        <div>
            <div class="flex items-center gap-2.5 text-xl font-black text-white">
                @if(\App\Models\Setting::find('logo')?->value)
                    <img class="h-9 w-9 rounded-xl object-contain" src="{{ Storage::url(\App\Models\Setting::find('logo')->value) }}" alt="Logo">
                @else
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-teal-500 text-slate-950">N</span>
                @endif
                {{ \App\Models\Setting::find('company_name')?->value ?? 'Nusa Travel' }}
            </div>
            <p class="mt-4 max-w-sm text-sm leading-7 text-slate-400">{{ \App\Models\Setting::find('footer')?->value ?? 'Teman perjalanan antarkota yang nyaman, aman, dan mudah dipesan.' }}</p>
        </div>
        <div>
            <h3 class="text-[0.7rem] font-extrabold tracking-[0.16em] text-white uppercase">Jelajahi</h3>
            <div class="mt-4 grid gap-2.5 text-sm">
                <a class="transition hover:text-teal-300" href="{{ route('travel.search') }}">Cari perjalanan</a>
                <a class="transition hover:text-teal-300" href="{{ route('home') }}#armada">Armada</a>
                <a class="transition hover:text-teal-300" href="{{ route('home') }}#cara">Cara pemesanan</a>
                <a class="transition hover:text-teal-300" href="{{ route('home') }}#cek">Cek booking</a>
            </div>
        </div>
        <div>
            <h3 class="text-[0.7rem] font-extrabold tracking-[0.16em] text-white uppercase">Hubungi kami</h3>
            <p class="mt-4 text-sm leading-7 text-slate-400">{{ \App\Models\Setting::find('company_address')?->value ?? '' }}</p>
            <div class="mt-3 grid gap-2 text-sm">
                <a class="transition hover:text-teal-300" href="{{ route('information', 'terms') }}">Syarat dan ketentuan</a>
                <a class="transition hover:text-teal-300" href="{{ route('information', 'cancellation') }}">Kebijakan pembatalan</a>
                <a class="transition hover:text-teal-300" href="{{ route('information', 'refund') }}">Ketentuan refund</a>
            </div>
            @if(\App\Models\Setting::find('whatsapp')?->value)
                <a class="mt-4 inline-flex items-center gap-2 rounded-xl bg-teal-500 px-4 py-2.5 text-xs font-extrabold text-slate-950 transition hover:bg-teal-400" href="https://wa.me/{{ preg_replace('/\D/', '', \App\Models\Setting::find('whatsapp')?->value) }}" target="_blank" rel="noopener">Chat WhatsApp ↗</a>
            @endif
        </div>
    </div>
    <div class="border-t border-white/10 py-5 text-center text-xs text-slate-500">
        © {{ date('Y') }} {{ \App\Models\Setting::find('company_name')?->value ?? 'Nusa Travel' }} · Foto armada ilustrasi: オーバードライブ83 / Wikimedia Commons (CC BY-SA 4.0)
    </div>
</footer>
</body>
</html>
