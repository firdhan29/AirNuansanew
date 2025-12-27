<?php

namespace App\Http\Controllers;

use App\Models\Warga;
use App\Models\User;
use App\Models\Expense; // Pastikan Model ini merujuk ke tabel 'expenses'
use App\Models\Income;  // Pastikan Model ini merujuk ke tabel 'incomes'
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

class WargaController extends Controller
{
    public function index(Request $request)
    {
        $query = Warga::query();

        // Logika Pencarian
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('blok', 'like', '%' . $search . '%')
                  ->orWhere('nomor', 'like', '%' . $search . '%');
            });
        }

        $wargas = $query->orderBy('blok', 'asc')
                        ->orderBy('nomor', 'asc')
                        ->get();

        // =============================================================
        // FIX: AMBIL DATA DARI TABEL MASING-MASING (SESUAI PHPMYADMIN)
        // =============================================================

        // 1. Ambil dari tabel 'expenses' melalui Model Expense
        $expenses = Expense::orderBy('date', 'desc')->get();

        // 2. Ambil dari tabel 'incomes' melalui Model Income
        $incomes = Income::orderBy('date', 'desc')->get();

        $users = User::all();

        return Inertia::render('Dashboard', [
            'wargas' => $wargas,
            'users' => $users,
            'expenses' => $expenses,
            'incomes' => $incomes,
            'canLogin' => Route::has('login'),
            'user' => Auth::user(),
            'filters' => $request->only(['search']),
        ]);
    }

    // =================================================================
    // 2. SIMPAN WARGA BARU
    // =================================================================
    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'blok' => 'required|string|max:10',
            'nomor' => 'required',
            'tarif' => 'nullable|integer',
        ]);

        $warga = Warga::create([
            'nama' => $request->nama,
            'blok' => strtoupper($request->blok),
            'nomor' => $request->nomor,
            'telepon' => $request->telepon,
            'tarif' => $request->tarif ?? 20000,
            'payment_history' => []
        ]);

        return redirect()->back()->with('message', 'Warga berhasil ditambahkan');
    }

    // =================================================================
    // 3. UPDATE DATA
    // =================================================================
    public function update(Request $request, $id)
    {
        $warga = Warga::find($id);
        if (!$warga) return response()->json(['message' => 'Data tidak ditemukan'], 404);

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

        return redirect()->back()->with('message', 'Data berhasil diupdate');
    }

    // =================================================================
    // 4. HAPUS DATA
    // =================================================================
    public function destroy($id)
    {
        $warga = Warga::find($id);
        if ($warga) {
            $warga->delete();
        }
        return redirect()->back();
    }

    // =================================================================
    // 5. PAY (PEMBAYARAN)
    // =================================================================
    public function pay(Request $request, $id)
    {
        // Kosong karena logika ada di Route Closure (web.php)
    }
}