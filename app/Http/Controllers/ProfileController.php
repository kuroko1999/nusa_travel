<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view($user->role === 'super_admin' ? 'admin.profile' : 'customer.profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)], 'phone' => 'required|string|max:30']);
        $user->update($data);

        return back()->with('status', 'Profil diperbarui.');
    }

    public function password(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok.',
        ]);
        $user->update(['password' => $data['password']]);
        // Hilangkan sesi lain setelah kata sandi berubah; sesi yang sedang dipakai tetap hidup.
        Auth::logoutOtherDevices($data['password']);

        return back()->with('status', 'Kata sandi berhasil diganti.');
    }
}
