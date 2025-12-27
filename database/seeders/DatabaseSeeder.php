<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Resident;
use App\Models\Transaction;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. BUAT AKUN ADMIN (Wajib ada biar bisa login & input data)
        // Silakan ganti Email & Password sesuai keinginanmu untuk Data Real
        \App\Models\User::factory()->create([
            'name' => 'Admin Nuansa Manisi',
            'email' => 'firdhanv@gmail.com', // Ganti dengan email aslimu
            'password' => bcrypt('@Bandung12'),     // Ganti dengan password rahasia
        ]);

        // 2. DATA DUMMY (HAPUS ATAU KOMENTARI BAGIAN INI)
        // Resident::factory(30)->create();    <-- JANGAN DIJALANKAN LAGI
        // Transaction::factory(50)->create(); <-- JANGAN DIJALANKAN LAGI
    }
}