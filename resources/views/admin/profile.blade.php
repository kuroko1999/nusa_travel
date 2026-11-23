@extends('layouts.admin')
@section('title', 'Profil & Kata Sandi')
@section('admin-content')
<div class="mx-auto max-w-2xl">
    <span class="eyebrow">AKUN ADMIN</span>
    <h1 class="heading mt-2">Profil Saya</h1>
    <p class="mt-2 text-slate-600">Ubah data akun dan kata sandi Super Admin. Perubahan kata sandi mengakhiri sesi login lain.</p>
    @include('partials.profile-forms')
</div>
@endsection
