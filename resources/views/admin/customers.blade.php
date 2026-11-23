@extends('layouts.admin') @section('title','Pelanggan') @section('admin-content')<span class="eyebrow">DATA</span>
<h1 class="heading mt-2">Pelanggan</h1>
<form class="mt-6 flex flex-wrap gap-3" method="get">
<input class="input max-w-sm" name="q" value="{{ request('q') }}" placeholder="Cari nama, email, atau WhatsApp">
<button class="btn-primary">Cari</button>
@if(request('q'))<a class="link self-center" href="{{ route('admin.customers') }}">Reset</a>@endif
</form>
<div class="mt-6 overflow-x-auto rounded-2xl bg-white p-5">
<table class="table">
<thead>
<tr>
<th>Nama</th>
<th>Email</th>
<th>WhatsApp</th>
<th>Pesanan</th>
<th>Total belanja</th>
<th>Terdaftar</th>
</tr>
</thead>
<tbody>@forelse($customers as $c)
<tr>
<td class="font-bold">{{ $c->name }}</td>
<td>{{ $c->email }}</td>
<td>{{ $c->phone }}</td>
<td>{{ $c->bookings_count }} pesanan</td>
<td>Rp{{ number_format((int) $c->total_spent,0,',','.') }}</td>
<td>{{ $c->created_at->format('d M Y') }}</td>
</tr>
@empty
<tr><td colspan="6">Belum ada pelanggan yang cocok.</td></tr>
@endforelse</tbody>
</table>
</div>
<div class="mt-5">{{ $customers->links() }}</div>@endsection
