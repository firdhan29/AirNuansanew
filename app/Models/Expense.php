<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    // Pastikan nama tabel sesuai dengan yang di phpMyAdmin
    protected $table = 'expenses'; 
    
    // Pastikan kolom yang boleh diisi terdaftar
    protected $fillable = ['description', 'amount', 'date'];
}