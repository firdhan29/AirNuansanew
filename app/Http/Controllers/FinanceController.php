<?php

namespace App\Http\Controllers;

use App\Models\Warga;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    // LOGIKA PEMBAYARAN (FIXED: Support Tahun)
    public function togglePayment(Request $request, $id)
    {
        $warga = Warga::findOrFail($id);

        // Ambil history lama atau array baru jika kosong
        $history = $warga->payment_history ?? [];
        
        // Input dari Frontend
        $month = $request->month; // contoh: 'jan'
        $year = $request->year;   // WAJIB ADA: contoh: '2025' atau '2026'
        $amount = $request->amount; 

        // 1. Validasi: Bulan dan Tahun wajib ada
        if (!$month || !$year) {
            return response()->json(['message' => 'Error: Bulan dan Tahun wajib dipilih'], 400);
        }

        // 2. BUAT KUNCI UNIK (Gabungan Bulan_Tahun)
        // Ini kuncinya! 'jan' jadi 'jan_2026' supaya tidak menimpa 'jan_2025'
        $paymentKey = strtolower($month) . '_' . $year;

        // 3. Cek Status Pembayaran
        if ($amount === null || $amount == 0) {
            // Jika amount null/0 = BATAL BAYAR (Hapus key)
            if (isset($history[$paymentKey])) {
                unset($history[$paymentKey]);
            }
            $message = "Pembayaran $month $year dibatalkan";
        } else {
            // Jika ada amount = BAYAR (Simpan nominal)
            $history[$paymentKey] = (int) $amount;
            $message = "Pembayaran $month $year berhasil: Rp " . number_format($amount);
        }

        // 4. Simpan ke Database
        $warga->payment_history = $history;
        $warga->save();

        return response()->json([
            'message' => $message,
            'data_history' => $history // Kirim balik history terbaru ke frontend
        ]);
    }
}