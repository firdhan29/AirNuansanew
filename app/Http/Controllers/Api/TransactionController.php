<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // 1. LIHAT SEMUA TRANSAKSI
    public function index()
    {
        // Urutkan dari tanggal terbaru
        return response()->json(Transaction::orderBy('date', 'desc')->get());
    }

    // 2. TAMBAH TRANSAKSI BARU
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense', // Hanya boleh 'income' atau 'expense'
            'category' => 'required|string',
            'date' => 'required|date',
        ]);

        $transaction = Transaction::create($validated);
        
        return response()->json([
            'message' => 'Transaksi berhasil disimpan',
            'data' => $transaction
        ], 201);
    }

    // 3. UPDATE TRANSAKSI
    public function update(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        $validated = $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense',
            'category' => 'required|string',
            'date' => 'required|date',
        ]);

        $transaction->update($validated);

        return response()->json([
            'message' => 'Transaksi berhasil diupdate',
            'data' => $transaction
        ]);
    }

    // 4. HAPUS TRANSAKSI
    public function destroy($id)
    {
        $transaction = Transaction::findOrFail($id);
        $transaction->delete();
        
        return response()->json(['message' => 'Transaksi berhasil dihapus']);
    }
}