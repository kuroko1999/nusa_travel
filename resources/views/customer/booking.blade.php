@php
    // Super Admin memakai shell panel admin; tamu dan pelanggan memakai situs publik.
    $lihatSebagaiAdmin = auth()->user()?->role === 'super_admin';
    $bingkai = $lihatSebagaiAdmin ? 'mx-auto max-w-7xl' : 'page';
@endphp
@extends($lihatSebagaiAdmin ? 'layouts.admin' : 'layouts.app')
@section('title','Detail Pesanan')
@section($lihatSebagaiAdmin ? 'admin-content' : 'content')
<div class="{{ $bingkai }}">
@if(auth()->user()?->role==='super_admin')
<a class="link" href="{{ route('admin.bookings') }}">‹ Pemesanan</a>
@elseif(auth()->check())
<a class="link" href="{{ route('customer.dashboard') }}">‹ Pesanan Saya</a>
@else
<a class="link" href="{{ route('booking.check') }}">‹ Cek booking</a>
@endif
@if($booking->user_id === null && auth()->guest())
<div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
    <h2 class="font-bold">Simpan kode booking Anda</h2>
    <p class="mt-2 text-sm">Untuk membuka pesanan, tiket, dan faktur dari perangkat lain, gunakan kode booking ini bersama nomor WhatsApp <strong>{{ $booking->phone }}</strong> pada halaman <a class="underline" href="{{ route('booking.check') }}">Cek Booking</a>. Akses dari perangkat ini berlaku 24 jam.</p>
    <p class="mt-2">Kode booking <strong>{{ $booking->code }}</strong></p>
</div>
@endif
<div class="mt-6 grid gap-7 lg:grid-cols-[1fr_340px]"><div><span class="eyebrow">DETAIL PESANAN</span><h1 class="heading mt-2">{{ $booking->code }}</h1><p class="mt-2 text-slate-600">{{ ucfirst(str_replace('_',' ',$booking->status)) }} · Pembayaran {{ ucfirst($booking->payment_status) }}</p><div class="mt-7 rounded-3xl bg-white p-7"><h2 class="text-xl font-black">Perjalanan</h2><div class="mt-4 grid gap-3 text-sm md:grid-cols-2"><p>Rute: <strong>{{ $booking->schedule->route->origin }} → {{ $booking->schedule->route->destination }}</strong></p><p>Berangkat: <strong>{{ $booking->schedule->departs_at->translatedFormat('d M Y H:i') }} WIB</strong></p><p>Armada: <strong>{{ $booking->schedule->vehicle->model }}</strong></p><p>Nomor polisi: <strong>{{ $booking->schedule->vehicle->plate }}</strong></p><p>Jemput: <strong>{{ $booking->pickup_address }}</strong></p><p>Antar: <strong>{{ $booking->dropoff_address }}</strong></p></div><h3 class="mt-7 font-bold">Penumpang</h3><div class="mt-3 divide-y">@foreach($booking->passengers as $p)<div class="flex justify-between py-3"><span>{{ $p->name }}</span><strong>Kursi {{ $p->seat->number }}</strong></div>@endforeach</div></div>@if(auth()->user()?->role==='super_admin')
@php
    $statusSaatIni = $booking->status;
    $sudahLunas = $booking->payment_status === 'lunas';
    $statusTerkunci = in_array($statusSaatIni, ['dibatalkan', 'kedaluwarsa', 'selesai'], true);
    $pilihanStatus = [];
    if (! $statusTerkunci && $sudahLunas) {
        if ($statusSaatIni === 'menunggu_pembayaran') {
            $pilihanStatus['dikonfirmasi'] = 'Konfirmasi pesanan';
        }
        $pilihanStatus['selesai'] = 'Tandai selesai';
    }
    if (! $sudahLunas && in_array($statusSaatIni, ['menunggu_pembayaran', 'kedaluwarsa'], true)) {
        $pilihanStatus['dibatalkan'] = 'Batalkan pesanan';
    }
@endphp
<div class="mt-6 rounded-3xl bg-white p-7">
<h2 class="text-xl font-black">Operasional admin</h2>
<p class="mt-2 text-sm text-slate-500">Status: <strong>{{ ucwords(str_replace('_',' ',$statusSaatIni)) }}</strong> · Pembayaran: <strong>{{ ucwords(str_replace('_',' ',$booking->payment_status)) }}</strong></p>
@if($pilihanStatus)
<form class="mt-4 flex flex-wrap items-center gap-3" method="post" action="{{ route('admin.bookings.status',$booking) }}">@csrf
<select class="input max-w-64" name="status" required>
<option value="">Pilih status baru…</option>
@foreach($pilihanStatus as $nilai=>$label)<option value="{{ $nilai }}">{{ $label }}</option>@endforeach
</select>
<button class="btn-primary">Simpan</button>
</form>
@else
<p class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Status pesanan ini tidak dapat diubah lagi dari halaman ini.</p>
@endif
@if(! $sudahLunas)
<p class="mt-3 text-sm text-amber-700">Pesanan belum lunas. Verifikasi pembayaran lebih dahulu di menu <a class="link" href="{{ route('admin.payments') }}">Pembayaran</a>; setelah lunas, status <em>dikonfirmasi</em> dan <em>selesai</em> tersedia di sini.</p>
@elseif(! $statusTerkunci)
<p class="mt-3 text-sm text-slate-500">Pesanan yang sudah lunas tidak dibatalkan dari sini — gunakan proses refund melalui menu <a class="link" href="{{ route('admin.payments') }}">Pembayaran</a>.</p>
@endif
@if($sudahLunas && ! in_array($statusSaatIni, ['dibatalkan', 'kedaluwarsa'], true))
<form class="mt-5 border-t pt-5" method="post" action="{{ route('admin.checkin',$booking) }}">@csrf
<span class="text-sm font-bold">Check-in penumpang</span>
<div class="mt-3 flex flex-wrap gap-4">@foreach($booking->passengers as $p)<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="passenger_ids[]" value="{{ $p->id }}" @checked($p->checked_in_at)>{{ $p->name }}</label>@endforeach</div>
<button class="btn-outline mt-4">Simpan check-in</button>
</form>
@endif
</div>
@endif
@if($booking->payment_status==='lunas')<div class="mt-6 rounded-3xl bg-teal-50 p-7"><h2 class="text-xl font-black">Dokumen perjalanan</h2><div class="mt-5 flex flex-wrap gap-3"><a class="btn-outline" href="{{ route('tickets.show',$booking) }}">Lihat tiket</a><a class="btn-outline" href="{{ route('tickets.pdf',$booking) }}">Download tiket PDF</a>@if($booking->invoice)<a class="btn-outline" href="{{ route('invoices.show',$booking->invoice) }}">Lihat faktur</a><a class="btn-outline" href="{{ route('invoices.pdf',$booking->invoice) }}">Download faktur PDF</a>@endif</div></div>@endif @if($booking->status==='selesai' && auth()->user()?->role==='pelanggan' && $booking->user_id===auth()->id())<div class="mt-6 rounded-3xl bg-white p-7"><h2 class="text-xl font-black">Bagikan pengalaman Anda</h2><form class="mt-4 grid gap-3" method="post" action="{{ route('reviews.store',$booking) }}">@csrf<select class="input" name="rating"><option value="5">5 bintang</option><option value="4">4 bintang</option><option value="3">3 bintang</option><option value="2">2 bintang</option><option value="1">1 bintang</option></select><textarea class="input" name="quote" minlength="10" maxlength="500" placeholder="Ceritakan perjalanan Anda" required></textarea><button class="btn-primary">Kirim ulasan</button></form></div>@endif</div><aside class="h-fit rounded-3xl bg-white p-7"><h2 class="text-xl font-black">Ringkasan pembayaran</h2><div class="mt-5 space-y-3 text-sm"><div class="flex justify-between"><span>Tiket ({{ $booking->passenger_count }} × Rp{{ number_format($booking->fare_snapshot,0,',','.') }})</span><strong>Rp{{ number_format($booking->fare_snapshot*$booking->passenger_count,0,',','.') }}</strong></div><div class="flex justify-between"><span>Jemput</span><span>Rp{{ number_format($booking->pickup_fee,0,',','.') }}</span></div><div class="flex justify-between"><span>Antar</span><span>Rp{{ number_format($booking->dropoff_fee,0,',','.') }}</span></div><div class="flex justify-between border-t pt-4 text-lg font-black"><span>Total</span><span>Rp{{ number_format($booking->total,0,',','.') }}</span></div></div>@if($booking->status==='menunggu_pembayaran')<p class="mt-5 text-sm text-amber-700">Bayar sebelum {{ $booking->expires_at->translatedFormat('d M Y H:i') }} WIB</p><form class="mt-5 grid gap-3" method="post" action="{{ route('payments.choose',$booking) }}">@csrf<button class="btn-primary" name="method" value="transfer">Transfer Bank</button><button class="btn-outline" name="method" value="midtrans">Bayar via Midtrans</button></form>@foreach($booking->payments->where('method','transfer') as $payment)<div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm"><h3 class="font-bold">Rekening tujuan</h3>@forelse(\App\Models\BankAccount::where('active',true)->get() as $bank)<p class="mt-2">{{ $bank->bank }} · {{ $bank->number }}<br>{{ $bank->holder }}</p>@empty<p>Rekening belum dikonfigurasi admin.</p>@endforelse
@if($payment->status==='pending')<form class="mt-4" method="post" enctype="multipart/form-data" action="{{ route('payments.proof',$payment) }}">@csrf<input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required><button class="btn-primary mt-3 w-full">Unggah bukti</button></form>@endif
@if($payment->proof_path)<p class="mt-3 text-teal-700">Bukti sudah diunggah. Menunggu verifikasi.</p>@endif</div>@endforeach<form method="post" action="{{ route('bookings.cancel',$booking) }}" class="mt-5">@csrf<button class="text-sm text-red-700 underline" onclick="return confirm('Batalkan pesanan ini?')">Batalkan pesanan</button></form>@endif</aside></div></div>@endsection
