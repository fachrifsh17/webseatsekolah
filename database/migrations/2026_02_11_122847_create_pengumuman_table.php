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
        Schema::create('pengumuman', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('judul', 255)->nullable();
            
            // Menggunakan text untuk isi pengumuman
            $table->text('isi_pengumuman')->nullable();
            
            $table->dateTime('tanggal_publikasi')->nullable();
            
            // tinyint(1) dipetakan ke boolean di Laravel
            $table->boolean('penting')->nullable();
            
            // created_at & updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};