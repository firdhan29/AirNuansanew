<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wargas', function (Blueprint $table) {
            $table->string('nama');
$table->string('blok');
$table->string('nomor');
$table->string('telepon')->nullable();
$table->integer('tarif')->default(20000);

// Gunakan 'json' jika pakai MySQL terbaru, atau 'text'/'longtext' jika server lama
$table->json('payment_history')->nullable(); // <--- Pastikan ini nullable
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wargas');
    }
};