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
        Schema::create('banner_pengumuman', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('judul', 255)->nullable();
            $table->string('url_link', 255)->nullable();
            $table->dateTime('aktif_sampai')->nullable();
            
            // Kolom foto dengan comment sesuai SQL Anda
            $table->string('foto', 255)->nullable()->comment('Banner/Slider foto');
            
            // created_at & updated_at menggunakan timestamp bawaan Laravel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banner_pengumuman');
    }
};