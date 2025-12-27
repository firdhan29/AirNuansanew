<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->enum('type', ['income', 'expense']); // Pemasukan atau Pengeluaran
        $table->string('category'); // Iuran, Perbaikan, Listrik, dll
        $table->decimal('amount', 15, 2);
        $table->date('date');
        $table->string('description');
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
