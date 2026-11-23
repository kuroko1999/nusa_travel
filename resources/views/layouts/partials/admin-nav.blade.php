@php
    $menuAktif = fn (string $href): bool => $urlSaatIni === $href || str_starts_with($urlSaatIni, $href.'/');
    $grupMenu = [
        'Operasional' => [
            'Dashboard' => route('admin.dashboard'),
            'Pemesanan' => route('admin.bookings'),
            'Pembayaran' => route('admin.payments'),
            'Faktur / Invoice' => route('admin.invoices'),
            'Data Pelanggan' => route('admin.customers'),
            'Laporan' => route('admin.reports'),
            'Ulasan Pelanggan' => route('admin.reviews'),
        ],
        'Data master' => [
            'Jadwal Keberangkatan' => route('admin.resources.index', 'schedules'),
            'Armada Kendaraan' => route('admin.resources.index', 'vehicles'),
            'Data Sopir' => route('admin.resources.index', 'drivers'),
            'Rute Perjalanan' => route('admin.resources.index', 'routes'),
            'Rekening Bank' => route('admin.resources.index', 'bank-accounts'),
        ],
        'Situs' => [
            'Pengaturan Website' => route('admin.settings'),
        ],
    ];
@endphp
<nav class="grid gap-4">
    @foreach($grupMenu as $judul => $menu)
        <div>
            <p class="px-3 text-xs font-bold tracking-[0.16em] text-slate-500 uppercase">{{ $judul }}</p>
            <div class="mt-2 grid gap-0.5">
                @foreach($menu as $label => $href)
                    @php
                        $aktif = $label === 'Dashboard' ? $urlSaatIni === $href : $menuAktif($href);
                    @endphp
                    <a href="{{ $href }}" @if($aktif) aria-current="page" @endif class="flex items-center justify-between gap-2 rounded-xl border-l-2 px-3 py-2 text-sm transition {{ $aktif ? 'border-teal-400 bg-white/10 font-bold text-white' : 'border-transparent font-medium text-slate-300 hover:bg-white/5 hover:text-white' }}">
                        <span>{{ $label }}</span>
                        @if($label === 'Pembayaran' && $pembayaranMenunggu > 0)
                            <span class="rounded-full bg-teal-400 px-2 py-0.5 text-xs font-black text-slate-950">{{ $pembayaranMenunggu }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>
