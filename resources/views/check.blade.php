@extends('layouts.app')
@section('title', 'Cek Booking')
@section('content')
<div class="page max-w-2xl">
    <h1 class="heading">Cek booking</h1>
    <form method="post" action="{{ route('booking.recover') }}" class="mt-6 grid gap-4">
        @csrf
        <label class="field">
            Kode booking
            <input name="code" value="{{ old('code', request('code')) }}" maxlength="40" required>
        </label>
        <label class="field">
            Kunci akses untuk pesanan tamu
            <input type="password" name="guest_key" maxlength="64" autocomplete="off" spellcheck="false">
        </label>
        <button class="btn-primary">Buka pesanan</button>
    </form>
</div>
@endsection
