<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warga extends Model
{
    use HasFactory;

    protected $table = 'wargas';

    protected $fillable = [
        'nama',
        'blok',
        'nomor',
        'telepon',
        'tarif',
        'payment_history',
    ];

    protected $casts = [
        // Mengubah JSON di database menjadi Array PHP otomatis
        'payment_history' => 'array', 
        'tarif' => 'integer',
    ];
}