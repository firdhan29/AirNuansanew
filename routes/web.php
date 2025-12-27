<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Warga;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WargaController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Web Routes (FINAL FIX)
|--------------------------------------------------------------------------
*/

// 1. DASHBOARD & REDIRECT
Route::get('/', [WargaController::class, 'index'])->name('dashboard');
Route::redirect('/dashboard', '/');

// 2. LOGIKA PEMBAYARAN (FIX: Menggunakan redirect agar data Vue terupdate otomatis)
Route::middleware('auth')->post('/wargas/pay/{id}', function (Request $request, $id) {
    $warga = Warga::findOrFail($id);

    $monthKey = $request->input('month');
    $amount = $request->input('amount');

    $history = $warga->payment_history ?? [];

    if ($amount === null) {
        if (isset($history[$monthKey])) unset($history[$monthKey]);
    } else {
        $history[$monthKey] = (int) $amount;
    }

    $warga->payment_history = $history;
    $warga->save();

    // PENTING: Kembali ke halaman sebelumnya agar Inertia melakukan refresh data props
    return back();
});

// 3. ROUTE CRUD WARGA & ADMIN (PROTECTED BY AUTH)
Route::middleware('auth')->group(function () {
    Route::post('/wargas/store', [WargaController::class, 'store']);
    Route::post('/wargas/update/{id}', [WargaController::class, 'update']);
    Route::post('/wargas/delete/{id}', [WargaController::class, 'destroy']);

    // 4. ROUTE ADMIN & KEUANGAN
    Route::post('/users/store', [UserController::class, 'store']);
    Route::post('/users/update/{id}', [UserController::class, 'update']);
    Route::post('/users/delete/{id}', [UserController::class, 'destroy']);

    // Rute Transaksi (Expenses & Incomes)
    Route::post('/expenses/store', [TransactionController::class, 'store']);
    Route::post('/expenses/update/{id}', [TransactionController::class, 'update']);
    Route::post('/expenses/delete/{id}', [TransactionController::class, 'destroy']);

    Route::post('/incomes/store', [TransactionController::class, 'store']);
    Route::post('/incomes/update/{id}', [TransactionController::class, 'update']);
    Route::post('/incomes/delete/{id}', [TransactionController::class, 'destroy']);
});

// 5. AUTHENTICATED ROUTES
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';