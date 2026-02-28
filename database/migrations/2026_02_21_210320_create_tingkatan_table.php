<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tingkatan', function (Blueprint $table) {
            // ID sebagai Primary Key
            $table->string('id', 10)->primary(); 
            // Nama Tingkatan (contoh: X, XI, XII, 10, 11, 12)
            $table->string('nama_tingkatan', 50); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tingkatan');
    }
};