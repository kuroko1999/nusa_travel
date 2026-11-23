@extends('layouts.app')
@section('title', 'Cek Booking')
@section('content')
<div class="page max-w-2xl">
    <h1 class="heading">Cek booking</h1>
    <p class="mt-3 text-slate-600">Pesanan yang dibuat tanpa akun dibuka dengan kode booking dan nomor WhatsApp yang Anda isi saat memesan.</p>
    <form method="post" action="{{ route('booking.recover') }}" class="mt-6 grid gap-4">
        @csrf
        <label class="field">
            Kode booking
            <input name="code" value="{{ old('code', request('code')) }}" maxlength="40" placeholder="TRV-..." required>
        </label>
        <label class="field">
            Nomor WhatsApp pemesan
            <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="30" placeholder="08xx xxxx xxxx" autocomplete="tel" inputmode="tel" required>
        </label>
        <button class="btn-primary">Buka pesanan</button>
    </form>
</div>
@endsection
