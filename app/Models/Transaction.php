<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    // Nama tabel di database (opsional jika sesuai standar Laravel)
    protected $table = 'transactions';

    // Kolom yang boleh diisi (Mass Assignment)
    protected $fillable = [
        'description',
        'amount',
        'type',      // income / expense
        'category',  // misal: 'Listrik', 'Kebersihan', 'Perbaikan'
        'date',
    ];

    protected $casts = [
        'amount' => 'integer', // atau 'decimal:2' jika butuh koma
        'date' => 'date',
    ];
}