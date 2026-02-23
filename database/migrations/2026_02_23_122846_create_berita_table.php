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
        Schema::create('berita', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('judul', 255)->nullable();
            
            // Menggunakan longText agar bisa menampung tulisan yang sangat panjang
            $table->longText('isi_berita')->nullable();
            
            $table->dateTime('tanggal_publikasi')->nullable();
            
            // Kolom foto utama dengan komentar
            $table->string('foto', 255)->nullable()->comment('Foto utama berita');
            
            // created_at & updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('berita');
    }
};