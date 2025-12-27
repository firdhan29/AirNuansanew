<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WargaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Lokasi file CSV (Pastikan file ada di folder storage/app/)
        $csvFile = storage_path('app/data_warga.csv');

        // Cek apakah file ada
        if (!file_exists($csvFile)) {
            $this->command->error("File CSV tidak ditemukan di: $csvFile");
            return;
        }

        // 2. Membaca file CSV
        $file = fopen($csvFile, 'r');
        
        // Lewati baris pertama (Header/Judul Kolom)
        fgetcsv($file); 

        // 3. Looping data per baris
        while (($data = fgetcsv($file, 2000, ',')) !== false) {
            
            // ASUMSI URUTAN KOLOM DI CSV:
            // Index 0: Blok (Contoh: C)
            // Index 1: Nomor (Contoh: 01)
            // Index 2: Nama Penghuni (Contoh: Bpk. Ahmad)
            // Index 3: Telepon (Contoh: 0812...)

            // Cek jika nama penghuni kosong atau "KOSONG", skip atau set null
            $nama = $data[2] ?? 'Tanpa Nama';
            $telepon = $data[3] ?? null;

            if (strtoupper($nama) === 'KOSONG') {
                $telepon = null; // Pastikan no hp null jika rumah kosong
            }

            // Insert ke Database
            DB::table('wargas')->insert([
                'blok'       => $data[0],
                'nomor'      => $data[1],
                'nama'       => $nama,
                'telepon'    => $this->bersihkanNomorHp($telepon),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        fclose($file);
        $this->command->info('Data warga berhasil diimport!');
    }

    /**
     * Helper kecil untuk membersihkan format nomor HP
     * Mengubah "0812-345" menjadi "0812345"
     */
    private function bersihkanNomorHp($noHp)
    {
        if (!$noHp) return null;
        // Hanya ambil angka
        return preg_replace('/[^0-9]/', '', $noHp);
    }
}