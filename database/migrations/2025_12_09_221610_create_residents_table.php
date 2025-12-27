<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('residents', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('block'); // Contoh: "A1"
        $table->string('phone')->nullable();
        // Kita simpan status bayar dalam JSON biar simpel & cepat
        // Contoh data: {"jan": true, "feb": false, ...}
        $table->json('payment_history')->nullable(); 
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
