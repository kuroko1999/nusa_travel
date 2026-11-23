<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('customer.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)], 'phone' => 'required|string|max:30']);
        $user->update($data);

        return back()->with('status', 'Profil diperbarui.');
    }
}
