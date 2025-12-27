<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    // Tambah Admin Baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Admin berhasil ditambahkan']);
    }

    // Hapus Admin
    public function destroy($id)
    {
        // Cegah hapus diri sendiri
        if (Auth::user()->id == $id) {
            return response()->json(['error' => 'Tidak bisa menghapus akun sendiri!'], 403);
        }

        User::findOrFail($id)->delete();
        return response()->json(['message' => 'Admin berhasil dihapus']);
    }

    // TAMBAHKAN FUNGSI INI:
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id, // Ignore email sendiri
        ];

        // Password hanya divalidasi jika diisi
        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $request->validate($rules);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        // Hanya update password jika diisi
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json(['message' => 'Data Admin berhasil diperbarui']);
    }
}
