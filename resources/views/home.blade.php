@extends('layouts.app')
@section('title', 'Beranda')
@section('content')
@php
    $heroImage = \App\Models\Setting::find('hero_image')?->value;
    $heroUrl = $heroImage ? Storage::url($heroImage) : asset('images/panther.jpg');
    $namaSitus = \App\Models\Setting::find('company_name')?->value ?? 'Nusa Travel';
    $kotaAsal = $routes->pluck('origin')->unique()->values();
    $kotaTujuan = $routes->pluck('destination')->unique()->values();
    $wa = \App\Models\Setting::find('whatsapp')?->value;
    $jadwalBerikutnya = $schedules->first();
@endphp

<div class="bg-[#f5f6f4] text-slate-900">

    {{-- HERO --}}
    <section class="relative isolate overflow-hidden bg-slate-950">
        <img class="absolute inset-0 h-full w-full object-cover object-center opacity-55" src="{{ $heroUrl }}" alt="Armada {{ $namaSitus }}">
        <div class="absolute inset-0 bg-[radial-gradient(120%_90%_at_15%_10%,rgba(13,148,136,.28),transparent_55%)]"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-950/85 to-slate-900/35"></div>
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-[#f5f6f4] to-transparent"></div>

        <div class="relative mx-auto max-w-7xl px-5 pt-14 pb-28 md:pt-20 md:pb-40">
            <div class="grid gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:items-end">

                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-[0.68rem] font-extrabold tracking-[0.18em] text-teal-300 uppercase backdrop-blur">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-400"></span>
                        Travel antarkota · Sumatera
                    </span>

                    <h1 class="mt-6 max-w-3xl text-[2.6rem] leading-[1.02] font-black tracking-[-0.04em] text-white sm:text-6xl lg:text-[4.2rem]">
                        Jalan jauh,<br>
                        <span class="text-teal-300">rasanya dekat.</span>
                    </h1>

                    <p class="mt-6 max-w-xl text-base leading-8 text-slate-300 sm:text-lg">
                        Pilih jadwal, tentukan kursi favorit, dan biarkan kami mengantar dari pintu rumah Anda sampai tujuan. {{ $namaSitus }} melayani perjalanan antarkota di Sumatera dengan armada yang rutin dirawat.
                    </p>

                    <div class="mt-9 flex flex-wrap items-center gap-3">
                        <a class="inline-flex items-center justify-center rounded-2xl bg-teal-500 px-6 py-3.5 text-sm font-extrabold text-slate-950 transition hover:bg-teal-400" href="{{ route('travel.search') }}">
                            Cari jadwal keberangkatan
                        </a>
                        <a class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-6 py-3.5 text-sm font-bold text-white transition hover:border-white/40 hover:bg-white/5" href="#jadwal">
                            Lihat jadwal terdekat
                        </a>
                    </div>

                    <dl class="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-white/10 pt-7">
                        <div>
                            <dt class="text-[0.66rem] font-bold tracking-[0.16em] text-slate-400 uppercase">Rute aktif</dt>
                            <dd class="mt-1.5 text-2xl font-black text-white">{{ $routes->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.66rem] font-bold tracking-[0.16em] text-slate-400 uppercase">Armada</dt>
                            <dd class="mt-1.5 text-2xl font-black text-white">{{ $vehicles->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.66rem] font-bold tracking-[0.16em] text-slate-400 uppercase">Jadwal siap</dt>
                            <dd class="mt-1.5 text-2xl font-black text-white">{{ $schedules->count() }}+</dd>
                        </div>
                    </dl>
                </div>

                {{-- Kartu keberangkatan berikutnya --}}
                @if($jadwalBerikutnya)
                <div class="rounded-[1.75rem] border border-white/15 bg-slate-950/55 p-6 shadow-2xl shadow-black/40 backdrop-blur-md">
                    <div class="flex items-center justify-between">
                        <span class="text-[0.66rem] font-bold tracking-[0.16em] text-teal-300 uppercase">Keberangkatan berikutnya</span>
                        <span class="rounded-full bg-teal-400/15 px-2.5 py-1 text-[0.66rem] font-extrabold tracking-wide text-teal-300">{{ $jadwalBerikutnya->departs_at->diffForHumans(null, true) }}</span>
                    </div>

                    <div class="mt-5 flex items-center gap-3 text-white">
                        <div class="min-w-0">
                            <p class="truncate text-lg font-black">{{ $jadwalBerikutnya->route->origin }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $jadwalBerikutnya->departs_at->format('H:i') }} WIB</p>
                        </div>
                        <div class="flex flex-1 items-center gap-2 text-teal-400">
                            <span class="h-px flex-1 bg-white/20"></span>
                            <span class="text-xs">→</span>
                            <span class="h-px flex-1 bg-white/20"></span>
                        </div>
                        <div class="min-w-0 text-right">
                            <p class="truncate text-lg font-black">{{ $jadwalBerikutnya->route->destination }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ $jadwalBerikutnya->arrives_at?->format('H:i') ?? '—' }} WIB</p>
                        </div>
                    </div>

                    <div class="mt-5 flex items-end justify-between border-t border-white/10 pt-4">
                        <div>
                            <p class="text-[0.66rem] font-bold tracking-[0.16em] text-slate-400 uppercase">{{ $jadwalBerikutnya->vehicle->model }}</p>
                            <p class="mt-1 text-2xl font-black text-white">Rp{{ number_format($jadwalBerikutnya->fare, 0, ',', '.') }}<span class="ml-1 text-xs font-semibold text-slate-400">/kursi</span></p>
                        </div>
                        <a class="rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold text-slate-950 transition hover:bg-teal-300" href="{{ route('schedules.show', $jadwalBerikutnya) }}">Pilih kursi</a>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </section>

    {{-- PENCARIAN --}}
    <section class="relative z-10 mx-auto -mt-20 max-w-6xl px-5 md:-mt-24">
        <form action="{{ route('travel.search') }}" method="get" class="rounded-[1.75rem] border border-slate-200/70 bg-white p-5 shadow-[0_24px_60px_-24px_rgba(15,41,54,.35)] md:p-7">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-black tracking-tight">Rencanakan perjalanan Anda</p>
                <p class="text-xs font-semibold text-slate-500">Kursi ditahan 20 menit setelah pesanan dibuat</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_0.9fr_0.7fr_1fr_auto]">
                <label class="field">Dari
                    <select name="origin">
                        <option value="">Semua kota</option>
                        @foreach($kotaAsal as $kota)
                            <option @selected(old('origin') === $kota)>{{ $kota }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Ke
                    <select name="destination">
                        <option value="">Semua kota</option>
                        @foreach($kotaTujuan as $kota)
                            <option @selected(old('destination') === $kota)>{{ $kota }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="field">Tanggal
                    <input type="date" name="date" min="{{ today()->toDateString() }}" value="{{ old('date') }}">
                </label>
                <label class="field">Penumpang
                    <input type="number" name="passengers" value="{{ old('passengers', 1) }}" min="1" max="12">
                </label>
                <label class="field">Armada
                    <select name="model">
                        <option value="">Semua mobil</option>
                        <option @selected(old('model') === 'Isuzu Panther')>Isuzu Panther</option>
                        <option @selected(old('model') === 'Toyota Avanza')>Toyota Avanza</option>
                    </select>
                </label>
                <button class="btn-primary h-[3.1rem] self-end whitespace-nowrap px-7">Cari travel</button>
            </div>
        </form>
    </section>

    {{-- RUTE FAVORIT --}}
    <section id="rute" class="scroll-mt-24 py-20 md:py-24">
        <div class="mx-auto max-w-7xl px-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="eyebrow">RUTE FAVORIT</span>
                    <h2 class="heading mt-2">Kota yang paling sering dituju</h2>
                </div>
                <a class="link text-sm" href="{{ route('travel.search') }}">Lihat semua jadwal →</a>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @forelse($routes->take(4) as $route)
                    <a class="group relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 transition hover:-translate-y-1 hover:border-teal-300 hover:shadow-[0_20px_45px_-24px_rgba(13,148,136,.55)]"
                       href="{{ route('travel.search', ['origin' => $route->origin, 'destination' => $route->destination]) }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="rounded-full bg-teal-50 px-3 py-1 text-[0.62rem] font-extrabold tracking-[0.12em] text-teal-800 uppercase">{{ $route->duration_minutes }} mnt</span>
                            <span class="text-teal-700 transition group-hover:translate-x-1">↗</span>
                        </div>
                        <p class="mt-6 text-xs font-bold tracking-[0.14em] text-slate-500 uppercase">{{ $route->origin }}</p>
                        <h3 class="mt-1 text-2xl font-black tracking-tight">{{ $route->destination }}</h3>
                        <p class="mt-6 border-t border-slate-100 pt-4 text-sm font-extrabold text-teal-700">Mulai Rp{{ number_format($route->base_fare, 0, ',', '.') }}</p>
                    </a>
                @empty
                    <p class="text-slate-500">Rute belum tersedia.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ARMADA --}}
    <section id="armada" class="scroll-mt-24 bg-white py-20 md:py-24">
        <div class="mx-auto max-w-7xl px-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="eyebrow">ARMADA KAMI</span>
                    <h2 class="heading mt-2">Kendaraan yang siap mengantar</h2>
                </div>
                <p class="max-w-sm text-sm leading-7 text-slate-600">Setiap unit diperiksa sebelum berangkat. Pilih kelas kendaraan yang paling sesuai dengan jumlah penumpang dan bagasi Anda.</p>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                @forelse($vehicles as $vehicle)
                    <article class="group overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-[#f8f9f7]">
                        <div class="relative h-64 overflow-hidden bg-slate-200">
                            @if($vehicle->photo)
                                <img class="h-full w-full object-cover transition duration-700 group-hover:scale-[1.03]" src="{{ str_starts_with($vehicle->photo, 'images/') ? asset($vehicle->photo) : Storage::url($vehicle->photo) }}" alt="{{ $vehicle->name }}">
                            @else
                                <div class="grid h-full place-items-center text-6xl">🚐</div>
                            @endif
                            <span class="absolute top-4 left-4 rounded-full bg-slate-950/85 px-3 py-1.5 text-[0.62rem] font-extrabold tracking-[0.14em] text-white uppercase backdrop-blur">{{ $vehicle->model }}</span>
                        </div>
                        <div class="p-6 sm:p-7">
                            <div class="flex items-start justify-between gap-4">
                                <h3 class="text-2xl font-black tracking-tight">{{ $vehicle->name }}</h3>
                                <span class="shrink-0 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-bold text-slate-700">{{ $vehicle->plate }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-7 text-slate-600">{{ $vehicle->facilities ?: 'Armada nyaman untuk perjalanan antarkota.' }}</p>
                            <div class="mt-5 flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-slate-700 ring-1 ring-slate-200">{{ $vehicle->capacity }} kursi penumpang</span>
                                <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-slate-700 ring-1 ring-slate-200">AC</span>
                                <span class="rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-slate-700 ring-1 ring-slate-200">Bagasi</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="text-slate-500">Armada belum tersedia.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- JADWAL --}}
    <section id="jadwal" class="scroll-mt-24 py-20 md:py-24">
        <div class="mx-auto max-w-7xl px-5">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="eyebrow">JADWAL TERDEKAT</span>
                    <h2 class="heading mt-2">Pilih keberangkatan Anda</h2>
                </div>
                <a class="link text-sm" href="{{ route('travel.search') }}">Semua jadwal →</a>
            </div>

            <div class="mt-10 overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white">
                @forelse($schedules as $s)
                    <a class="group flex flex-wrap items-center gap-x-6 gap-y-4 border-b border-slate-100 px-5 py-5 transition last:border-0 hover:bg-teal-50/50 sm:px-7"
                       href="{{ route('schedules.show', $s) }}">
                        <div class="flex w-16 shrink-0 flex-col items-center justify-center rounded-2xl bg-slate-950 py-2.5 text-white">
                            <span class="text-[0.6rem] font-bold tracking-[0.12em] text-teal-300 uppercase">{{ $s->departs_at->translatedFormat('M') }}</span>
                            <span class="text-xl leading-tight font-black">{{ $s->departs_at->format('d') }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-lg font-black tracking-tight">{{ $s->route->origin }} <span class="text-teal-600">→</span> {{ $s->route->destination }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-500">{{ $s->departs_at->translatedFormat('l, d F Y') }} · {{ $s->departs_at->format('H:i') }} WIB · {{ $s->vehicle->model }}</p>
                        </div>

                        <div class="text-left sm:text-right">
                            <p class="text-lg font-black">Rp{{ number_format($s->fare, 0, ',', '.') }}</p>
                            <p class="text-xs font-semibold text-slate-500">per kursi</p>
                        </div>

                        <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-extrabold text-slate-800 transition group-hover:border-teal-500 group-hover:bg-teal-500 group-hover:text-white">Pilih kursi</span>
                    </a>
                @empty
                    <p class="px-7 py-12 text-center text-slate-500">Belum ada jadwal mendatang. Silakan hubungi kami untuk informasi keberangkatan.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- CARA PEMESANAN --}}
    <section id="cara" class="scroll-mt-24 bg-slate-950 py-20 text-white md:py-24">
        <div class="mx-auto max-w-7xl px-5">
            <div class="max-w-2xl">
                <span class="text-[0.72rem] font-extrabold tracking-[0.18em] text-teal-300 uppercase">CARA PEMESANAN</span>
                <h2 class="mt-3 text-[2rem] leading-tight font-black tracking-[-0.04em] sm:text-[2.6rem]">Tiga langkah, siap berangkat</h2>
                <p class="mt-4 text-base leading-8 text-slate-300">Tanpa antre di loket. Semua bisa diselesaikan dari ponsel, termasuk pembayaran dan tiket digital.</p>
            </div>

            <div class="mt-12 grid gap-8 md:grid-cols-3">
                @foreach([
                    ['01', 'Cari perjalanan', 'Pilih kota asal, tujuan, tanggal, dan jumlah penumpang. Bandingkan jadwal serta armada yang tersedia.'],
                    ['02', 'Pilih kursi & isi data', 'Tentukan kursi untuk setiap penumpang, lampirkan alamat jemput dan antar, lalu lanjutkan tanpa perlu membuat akun.'],
                    ['03', 'Bayar, lalu berangkat', 'Selesaikan transfer bank atau Midtrans. Setelah terverifikasi, tiket dan faktur digital langsung bisa diunduh.'],
                ] as [$nomor, $judul, $teks])
                    <div class="relative border-t border-white/15 pt-6">
                        <span class="text-sm font-black tracking-[0.2em] text-teal-300">{{ $nomor }}</span>
                        <h3 class="mt-3 text-xl font-black">{{ $judul }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-400">{{ $teks }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-12 flex flex-wrap gap-3">
                <a class="rounded-2xl bg-teal-500 px-6 py-3.5 text-sm font-extrabold text-slate-950 transition hover:bg-teal-400" href="{{ route('travel.search') }}">Mulai pesan sekarang</a>
                @if($wa)
                    <a class="rounded-2xl border border-white/20 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/5" href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" target="_blank" rel="noopener">Tanya lewat WhatsApp</a>
                @endif
            </div>
        </div>
    </section>

    {{-- ULASAN --}}
    @if($reviews->isNotEmpty())
        <section class="py-20 md:py-24">
            <div class="mx-auto max-w-7xl px-5">
                <span class="eyebrow">CERITA PERJALANAN</span>
                <h2 class="heading mt-2">Kata pelanggan</h2>
                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    @foreach($reviews as $review)
                        <blockquote class="flex flex-col rounded-3xl border border-slate-200/80 bg-white p-7">
                            <div class="text-sm font-black text-amber-500">{{ str_repeat('★', $review->rating) }}</div>
                            <p class="mt-4 flex-1 text-[0.95rem] leading-7 text-slate-700">“{{ $review->quote }}”</p>
                            <div class="mt-6 border-t border-slate-100 pt-4 text-sm font-bold">{{ $review->user->name }}</div>
                        </blockquote>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CEK BOOKING --}}
    <section id="cek" class="scroll-mt-24 px-5 pb-20 md:pb-24">
        <div class="mx-auto max-w-7xl overflow-hidden rounded-[2rem] bg-gradient-to-br from-teal-800 via-teal-900 to-slate-900 px-6 py-12 text-white sm:px-10 md:px-14 md:py-16">
            <div class="grid gap-10 md:grid-cols-[1.1fr_0.9fr] md:items-center">
                <div>
                    <span class="text-[0.72rem] font-extrabold tracking-[0.18em] text-teal-200 uppercase">CEK BOOKING</span>
                    <h2 class="mt-3 text-[1.9rem] leading-tight font-black tracking-[-0.035em] sm:text-4xl">Sudah pesan? Lihat status perjalanan Anda.</h2>
                    <p class="mt-4 max-w-lg text-sm leading-7 text-teal-100">Masukkan kode booking, lalu nomor WhatsApp yang Anda pakai saat memesan. Tidak perlu akun — pesanan tamu juga bisa dibuka dari perangkat lain.</p>
                </div>
                <form class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur" method="get" action="{{ route('booking.check') }}">
                    <label class="block text-xs font-bold tracking-[0.12em] text-teal-100 uppercase" for="kode-cek">Kode booking</label>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input id="kode-cek" class="input border-white/20 text-slate-900 placeholder:text-slate-400" placeholder="TRV-..." name="code" required>
                        <button class="btn-primary whitespace-nowrap">Cek booking</button>
                    </div>
                    <p class="mt-3 text-xs text-teal-200/90">Contoh kode: TRV-261123-QJROO2B</p>
                </form>
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="pb-20 md:pb-24">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 md:grid-cols-[0.8fr_1.2fr]">
            <div>
                <span class="eyebrow">FAQ</span>
                <h2 class="heading mt-2">Pertanyaan umum</h2>
                <p class="mt-4 max-w-sm text-sm leading-7 text-slate-600">Belum menemukan jawaban? Tim kami siap membantu lewat WhatsApp pada jam operasional.</p>
            </div>
            <div class="space-y-3">
                @foreach([
                    'Apakah harus punya akun untuk memesan?' => 'Tidak. Anda dapat memesan sebagai tamu. Simpan kode booking dan gunakan nomor WhatsApp yang Anda isi untuk membuka pesanan kembali kapan saja.',
                    'Kapan tiket dan faktur tersedia?' => 'Setelah pembayaran diverifikasi. Dokumen dapat dilihat, diunduh, dan dicetak dari halaman pesanan Anda.',
                    'Berapa lama kursi ditahan?' => 'Kursi ditahan selama 20 menit sejak pesanan dibuat. Jika pembayaran belum selesai, reservasi berakhir otomatis dan kursi dilepas.',
                    'Metode pembayaran apa saja yang diterima?' => 'Transfer bank manual dengan unggah bukti, atau pembayaran daring melalui Midtrans (QRIS, virtual account, dan e-wallet sesuai ketersediaan).',
                    'Apakah bisa menjemput di rumah?' => 'Bisa. Isi alamat penjemputan dan pengantaran saat memesan; biaya jemput dan antar mengikuti pengaturan yang berlaku.',
                ] as $tanya => $jawab)
                    <details class="group rounded-2xl border border-slate-200/80 bg-white px-6 py-5 open:border-teal-300">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[0.95rem] font-extrabold">
                            {{ $tanya }}
                            <span class="text-lg font-normal text-teal-700 transition group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-4 text-sm leading-7 text-slate-600">{{ $jawab }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- KONTAK --}}
    <section class="px-5 pb-20 md:pb-24">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 rounded-[2rem] border border-slate-200/80 bg-white px-6 py-10 sm:px-10 md:flex-row md:items-center md:justify-between">
            <div>
                <span class="eyebrow">BANTUAN PERJALANAN</span>
                <h2 class="mt-2 text-2xl font-black tracking-tight sm:text-3xl">Siap membantu perjalanan Anda</h2>
                <p class="mt-2 max-w-xl text-sm leading-7 text-slate-600">Hubungi tim {{ $namaSitus }} untuk informasi jadwal, titik penjemputan, atau pertanyaan pembayaran.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($wa)
                    <a class="btn-primary px-6 py-3.5" href="https://wa.me/{{ preg_replace('/\D/', '', $wa) }}" target="_blank" rel="noopener">Chat WhatsApp</a>
                @else
                    <span class="text-sm text-slate-500">Nomor WhatsApp akan tersedia segera.</span>
                @endif
                <a class="btn-outline px-6 py-3.5" href="{{ route('travel.search') }}">Lihat jadwal</a>
            </div>
        </div>
    </section>
</div>
@endsection
