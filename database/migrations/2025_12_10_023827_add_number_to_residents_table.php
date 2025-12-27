<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('residents', function (Blueprint $table) {
        // Menambah kolom 'number' setelah kolom 'block'
        $table->string('number')->nullable()->after('block');
    });
}

public function down(): void
{
    Schema::table('residents', function (Blueprint $table) {
        $table->dropColumn('number');
    });
}
};
