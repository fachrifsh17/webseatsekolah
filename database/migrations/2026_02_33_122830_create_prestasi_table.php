<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prestasi', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('judul', 255)->nullable();
            
            // Kolom tahun menggunakan tipe year
            $table->year('tahun')->nullable();
            
            $table->string('tingkat', 50)->nullable(); // Nasional, Provinsi, dll
            
            // Enum kategori prestasi
            $table->enum('kategori', ['Siswa', 'Sekolah', 'Guru'])->nullable();
            
            $table->string('foto', 255)->nullable()->comment('Foto utama prestasi');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestasi');
    }
};