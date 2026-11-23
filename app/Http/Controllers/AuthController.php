<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function registerForm()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|unique:users,email', 'phone' => 'required|string|max:30', 'password' => ['required', 'confirmed', Password::min(8)]]);
        $user = User::create($data + ['role' => 'pelanggan']);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('customer.dashboard'));
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(Auth::user()->role === 'super_admin' ? route('admin.dashboard') : route('customer.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
