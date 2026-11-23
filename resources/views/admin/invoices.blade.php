@extends('layouts.admin') @section('title','Faktur') @section('admin-content')<span class="eyebrow">DOKUMEN</span>
<h1 class="heading mt-2">Faktur / Invoice</h1>
<form class="mt-6 flex flex-wrap gap-3" method="get">
<input class="input max-w-sm" name="number" value="{{ request('number') }}" placeholder="Cari nomor faktur, kode booking, atau pelanggan">
<button class="btn-primary">Cari</button>
@if(request('number'))<a class="link self-center" href="{{ route('admin.invoices') }}">Reset</a>@endif
</form>
<div class="mt-6 overflow-x-auto rounded-2xl bg-white p-5">
<table class="table">
<thead>
<tr>
<th>Nomor</th>
<th>Pelanggan</th>
<th>Booking</th>
<th>Tanggal</th>
<th>Total</th>
<th>Status</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>@forelse($invoices as $invoice)
<tr>
<td class="font-bold">{{ $invoice->number }}</td>
<td>{{ $invoice->booking->customerName() }}</td>
<td><a class="link" href="{{ route('bookings.show',$invoice->booking) }}">{{ $invoice->booking->code }}</a></td>
<td>{{ $invoice->issued_at->format('d M Y') }}</td>
<td>Rp{{ number_format((int) data_get($invoice->snapshot,'total',0),0,',','.') }}</td>
<td class="{{ $invoice->status==='refund' ? 'text-red-700' : 'text-teal-700' }}">{{ ucfirst($invoice->status) }}</td>
<td>
<a class="link" href="{{ route('invoices.show',$invoice) }}">Lihat</a> · <a class="link" href="{{ route('invoices.pdf',$invoice) }}">PDF</a>
</td>
</tr>@empty<tr>
<td colspan="7">Belum ada faktur.</td>
</tr>@endforelse</tbody>
</table>
</div>
<div class="mt-5">{{ $invoices->links() }}</div>@endsection
