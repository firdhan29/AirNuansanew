<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Income;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        $isExpense = str_contains($request->path(), 'expenses');

        if ($isExpense) {
            Expense::create($request->only(['description', 'amount', 'date']));
        } else {
            Income::create($request->only(['description', 'amount', 'date']));
        }

        // PERBAIKAN: Gunakan redirect back agar Inertia merefresh data otomatis di Vue
        return back()->with('message', $isExpense ? 'Pengeluaran berhasil ditambahkan' : 'Pemasukan berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        $isExpense = str_contains($request->path(), 'expenses');

        if ($isExpense) {
            $item = Expense::findOrFail($id);
        } else {
            $item = Income::findOrFail($id);
        }

        $item->update($request->only(['description', 'amount', 'date']));

        // PERBAIKAN: Gunakan redirect back
        return back()->with('message', $isExpense ? 'Pengeluaran berhasil diperbarui' : 'Pemasukan berhasil diperbarui');
    }

    public function destroy($id) {
    try {
        $item = str_contains(request()->path(), 'expenses') 
                ? \App\Models\Expense::findOrFail($id) 
                : \App\Models\Income::findOrFail($id);
        $item->delete();

        // Mengirim flash message ke session
        return back()->with('message', 'Data berhasil dihapus selamanya!');
    } catch (\Exception $e) {
        return back()->with('error', 'Gagal menghapus data. Silakan coba lagi.');
    }
}
}