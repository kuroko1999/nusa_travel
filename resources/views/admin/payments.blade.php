@extends('layouts.admin') @section('title','Pembayaran') @section('admin-content')<span class="eyebrow">MANAJEMEN</span>
<h1 class="heading mt-2">Pembayaran</h1>
<p class="mt-2 text-sm text-slate-600">Verifikasi transfer bank yang menunggu bukti. Setujui setelah nominal dan bukti sesuai.</p>
<div class="mt-7 space-y-4">@forelse($payments as $p)
@php
    $booking = $p->booking;
    $alasanTidakBisa = null;
    if (! $p->proof_path) {
        $alasanTidakBisa = 'Bukti transfer belum diunggah pelanggan.';
    } elseif ($p->amount !== $booking->total) {
        $alasanTidakBisa = 'Nominal tidak cocok dengan total pesanan.';
    } elseif (in_array($booking->status, ['dibatalkan', 'kedaluwarsa'], true)) {
        $alasanTidakBisa = 'Pesanan sudah dibatalkan atau kedaluwarsa.';
    } elseif ($booking->payment_status === 'lunas') {
        $alasanTidakBisa = 'Pesanan sudah lunas melalui pembayaran lain.';
    } elseif ($booking->expires_at->isPast()) {
        $alasanTidakBisa = 'Batas waktu pembayaran pesanan sudah lewat.';
    }
@endphp
<div class="rounded-2xl bg-white p-6 grid gap-4 lg:grid-cols-[1fr_auto]">
<div>
<p class="text-xs font-bold text-teal-700">{{ strtoupper($p->method) }} · {{ ucfirst($p->status) }}</p>
<a class="mt-1 block text-lg font-black" href="{{ route('bookings.show',$booking) }}">{{ $booking->code }}</a>
<p class="mt-1 text-sm text-slate-500">{{ $booking->customerName() }} · Rp{{ number_format($p->amount,0,',','.') }}</p>
<p class="mt-1 text-sm text-slate-500">{{ $booking->schedule->route->origin }} → {{ $booking->schedule->route->destination }} · {{ $booking->schedule->departs_at->format('d M Y H:i') }} WIB</p>
<p class="mt-2 text-xs text-slate-500">Status pesanan: <strong>{{ str_replace('_',' ',$booking->status) }}</strong> · Pembayaran: <strong>{{ str_replace('_',' ',$booking->payment_status) }}</strong> · Total: <strong>Rp{{ number_format($booking->total,0,',','.') }}</strong></p>
@if($p->proof_path)<a class="link mt-2 inline-block" href="{{ route('admin.payments.proof',$p) }}">Unduh bukti transfer</a>@endif
@if($p->rejection_reason)<p class="mt-2 text-red-700">Ditolak: {{ $p->rejection_reason }}</p>@endif
@if($p->method==='transfer' && $p->status==='pending' && $alasanTidakBisa)<p class="mt-3 rounded-xl bg-amber-50 px-4 py-2 text-sm text-amber-800">{{ $alasanTidakBisa }}</p>@endif
</div>
@if($p->method==='transfer' && $p->status==='pending')
<div class="flex flex-col gap-2 lg:w-64">
<form method="post" action="{{ route('admin.payments.approve',$p) }}">@csrf
<button class="btn-primary w-full" onclick="return confirm('Setujui pembayaran ini?')" @disabled($alasanTidakBisa)>Setujui</button>
</form>
<form class="flex gap-2" method="post" action="{{ route('admin.payments.reject',$p) }}">@csrf
<input class="input" name="reason" placeholder="Alasan penolakan" required>
<button class="btn-outline">Tolak</button>
</form>
</div>
@endif
</div>
@empty<div class="rounded-2xl bg-white p-8 text-slate-500">Belum ada pembayaran.</div>
@endforelse</div>
<div class="mt-5">{{ $payments->links() }}</div>@endsection
