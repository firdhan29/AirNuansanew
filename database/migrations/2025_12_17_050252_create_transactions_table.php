<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Tambahkan baris ini: Hapus tabel jika sudah ada
        Schema::dropIfExists('transactions'); 

        // Lalu buat baru
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['income', 'expense']);
            $table->string('category');
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
