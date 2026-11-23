@extends('layouts.admin') @section('title','Pemesanan') @section('admin-content')<span class="eyebrow">MANAJEMEN</span>
<h1 class="heading mt-2">Pemesanan</h1>
<form method="get" class="mt-6 flex flex-wrap gap-3">
<input class="input max-w-xs" name="code" placeholder="Cari kode booking" value="{{ request('code') }}">
<select class="input max-w-xs" name="status">
<option value="">Semua status</option>
@foreach(['menunggu_pembayaran','dikonfirmasi','dibatalkan','kedaluwarsa','selesai'] as $status)
<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>
@endforeach
</select>
<button class="btn-primary">Cari</button>
</form>
<div class="mt-6 overflow-x-auto rounded-2xl bg-white p-5">
<table class="table">
<thead>
<tr>
<th>Kode</th>
<th>Pelanggan</th>
<th>Jadwal</th>
<th>Status</th>
<th>Pembayaran</th>
<th>Total</th>
<th>Dokumen</th>
</tr>
</thead>
<tbody>@forelse($bookings as $b)
<tr>
<td>
<a class="link font-bold" href="{{ route('bookings.show',$b) }}">{{ $b->code }}</a><br>
<span class="text-xs text-slate-500">{{ $b->created_at->format('d M Y H:i') }}</span>
</td>
<td>{{ $b->customerName() }}<br><span class="text-xs text-slate-500">{{ $b->phone }}</span></td>
<td>{{ $b->schedule->route->origin }} → {{ $b->schedule->route->destination }}<br>
<span class="text-xs text-slate-500">{{ $b->schedule->departs_at->format('d M Y H:i') }} WIB</span></td>
<td class="{{ $b->status==='dibatalkan' ? 'text-red-700' : ($b->status==='menunggu_pembayaran' ? 'text-amber-700' : '') }}">{{ ucwords(str_replace('_',' ',$b->status)) }}</td>
<td class="{{ $b->payment_status==='lunas' ? 'text-teal-700' : ($b->payment_status==='pending' ? 'text-amber-700' : 'text-slate-500') }}">
{{ ucwords(str_replace('_',' ',$b->payment_status)) }}
@if($b->payment_status!=='lunas' && $b->expires_at->isPast() && $b->status==='menunggu_pembayaran')<br><span class="text-xs">Lewat batas waktu</span>@endif
</td>
<td>Rp{{ number_format($b->total,0,',','.') }}</td>
<td>
<a class="link" href="{{ route('bookings.show',$b) }}">Detail</a>
@if($b->payment_status==='lunas')
<br><a class="link" href="{{ route('tickets.pdf',$b) }}">Tiket</a>
@if($b->invoice)<br><a class="link" href="{{ route('invoices.pdf',$b->invoice) }}">Faktur</a>@endif
@endif
</td>
</tr>@empty<tr>
<td colspan="7">Belum ada pesanan.</td>
</tr>@endforelse</tbody>
</table>
</div>
<div class="mt-5">{{ $bookings->links() }}</div>@endsection
