@extends('layouts.app')
@section('title', 'Profil')
@section('content')
<div class="page max-w-2xl">
    <span class="eyebrow">AKUN SAYA</span>
    <h1 class="heading mt-2">Profil Saya</h1>
    @include('partials.profile-forms')
</div>
@endsection
