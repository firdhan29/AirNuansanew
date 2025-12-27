<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warga;
use Illuminate\Http\Request;

class ResidentController extends Controller
{
    // 1. GET ALL RESIDENTS (Data Warga + History)
    public function index(Request $request)
    {
        $query = Warga::query();

        // Fitur Search (Nama atau Blok)
        if ($request->search) {
            $query->where('nama', 'like', '%' . $request->search . '%')
                  ->orWhere('blok', 'like', '%' . $request->search . '%');
        }

        // Return JSON
        return response()->json($query->orderBy('blok', 'asc')->get());
    }

    // 2. CREATE RESIDENT (Simpan Warga)
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'blok' => 'required|string',
            'nomor' => 'required',
            'tarif' => 'nullable|integer',
        ]);

        $warga = Warga::create([
            'nama' => $request->nama,
            'blok' => strtoupper($request->blok),
            'nomor' => $request->nomor,
            'telepon' => $request->telepon,
            'tarif' => $request->tarif ?? 20000,
            'payment_history' => [] // Array kosong default
        ]);

        return response()->json(['message' => 'Warga berhasil ditambahkan', 'data' => $warga], 201);
    }

    // 3. UPDATE RESIDENT (Edit Warga)
    public function update(Request $request, $id)
    {
        $warga = Warga::findOrFail($id);

        $request->validate([
            'nama' => 'required|string',
            'blok' => 'required|string',
            'nomor' => 'required',
        ]);

        $warga->update([
            'nama' => $request->nama,
            'blok' => strtoupper($request->blok),
            'nomor' => $request->nomor,
            'telepon' => $request->telepon,
            'tarif' => $request->tarif,
        ]);

        return response()->json(['message' => 'Data berhasil diupdate', 'data' => $warga]);
    }

    // 4. DELETE RESIDENT (Hapus Warga)
    public function destroy($id)
    {
        $warga = Warga::findOrFail($id);
        $warga->delete();
        return response()->json(['message' => 'Warga dihapus']);
    }

    // 5. UPDATE PAYMENT (Hanya satu fungsi ini yang dipakai)
    public function updatePayment(Request $request, $id)
    {
        $warga = Warga::findOrFail($id);
        
        // 1. Ambil history lama (atau array kosong jika belum ada)
        $history = $warga->payment_history ?? [];

        // 2. Ambil Input dari Frontend
        $month = $request->month; // contoh: 'jan'
        $year  = $request->year;  // WAJIB: contoh '2026'
        $amount = $request->amount;

        // Validasi
        if (!$month || !$year) {
            return response()->json(['message' => 'Bulan dan Tahun wajib diisi'], 400);
        }

        // 3. Buat Key Unik (jan_2026) agar tidak menimpa tahun lain
        $paymentKey = strtolower($month) . '_' . $year;

        // 4. Logika Simpan/Hapus
        if ($amount === null || $amount == 0) {
            // Hapus pembayaran (Batal)
            if (isset($history[$paymentKey])) {
                unset($history[$paymentKey]);
            }
            $message = "Pembayaran dibatalkan";
        } else {
            // Simpan pembayaran
            $history[$paymentKey] = (int) $amount;
            $message = "Pembayaran berhasil";
        }

        // 5. Simpan ke Database
        $warga->payment_history = $history;
        $warga->save();

        return response()->json([
            'message' => $message,
            'data' => $warga
        ]);
    }
}