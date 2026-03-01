<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            
            // --- PERUBAHAN DI SINI ---
            // Gunakan string() untuk menyesuaikan dengan id tahun_ajaran yang berupa teks
            $table->string('tahun_ajaran_id');
            
            // Tambahkan constraint manual karena tidak menggunakan foreignId()
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->onDelete('cascade');
            
            // Nama Semester menggunakan teks biasa (string)
            $table->string('nama', 20); // Bisa diisi 'Ganjil', 'Genap', atau teks lainnya
            
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
    }
};