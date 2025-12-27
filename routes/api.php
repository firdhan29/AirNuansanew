<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResidentController;
use App\Http\Controllers\Api\TransactionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 1. User Auth (Sanctum)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// 2. RESIDENTS (Manajemen Warga)
// URL: /api/residents/...
Route::prefix('residents')->group(function () {
    Route::get('/', [ResidentController::class, 'index']);          // Ambil Data
    Route::post('/', [ResidentController::class, 'store']);         // Simpan Warga Baru
    Route::put('/{id}', [ResidentController::class, 'update']);     // Edit Warga
    Route::delete('/{id}', [ResidentController::class, 'destroy']); // Hapus Warga
    
    // Route Pembayaran Standar
    Route::post('/{id}/pay', [ResidentController::class, 'updatePayment']); 
});


// 3. ROUTE DARURAT (Agar Frontend Tidak Error)
// Karena di log error frontend Anda menembak ke '/wargas/pay/...', 
// kita tambahkan jalur ini supaya request tersebut tetap diterima.
Route::post('/wargas/pay/{id}', [ResidentController::class, 'updatePayment']);


// 4. TRANSACTIONS (Keuangan/Kas)
// URL: /api/transactions/...
Route::prefix('transactions')->group(function () {
    Route::get('/', [TransactionController::class, 'index']);
    Route::post('/', [TransactionController::class, 'store']);
    Route::put('/{id}', [TransactionController::class, 'update']);
    Route::delete('/{id}', [TransactionController::class, 'destroy']);
});