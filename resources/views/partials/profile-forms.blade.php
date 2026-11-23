{{-- Formulir profil dipakai pelanggan maupun admin, hanya shell-nya yang berbeda. --}}
<form class="mt-7 space-y-5 rounded-3xl bg-white p-8" method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('PUT')
    <label class="field">Nama lengkap
        <input name="name" value="{{ old('name', $user->name) }}" required>
    </label>
    <label class="field">Email
        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
    </label>
    <label class="field">WhatsApp
        <input name="phone" value="{{ old('phone', $user->phone) }}" required>
    </label>
    <button class="btn-primary">Simpan profil</button>
</form>

<form class="mt-7 space-y-5 rounded-3xl bg-white p-8" method="post" action="{{ route('profile.password') }}">
    @csrf
    @method('PUT')
    <div>
        <h2 class="text-xl font-black">Ganti kata sandi</h2>
        <p class="mt-2 text-sm text-slate-600">Minimal 8 karakter. Setelah kata sandi diganti, semua sesi login lain otomatis keluar.</p>
    </div>
    <label class="field">Kata sandi saat ini
        <input type="password" name="current_password" autocomplete="current-password" required>
    </label>
    <label class="field">Kata sandi baru
        <input type="password" name="password" autocomplete="new-password" required>
    </label>
    <label class="field">Ulangi kata sandi baru
        <input type="password" name="password_confirmation" autocomplete="new-password" required>
    </label>
    <button class="btn-primary">Perbarui kata sandi</button>
</form>
