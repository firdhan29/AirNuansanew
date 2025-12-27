<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // <--- TAMBAHKAN INI (1)
use Illuminate\Database\Eloquent\Model;

class Resident extends Model
{
    use HasFactory; // <--- TAMBAHKAN INI (2)

    // Supaya aman saat pengisian data, kita buka proteksinya
    protected $guarded = []; 
    
    // Karena kita simpan history sebagai JSON, kita harus 'casting'
    protected $casts = [
        'payment_history' => 'array',
    ];
}