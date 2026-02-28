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
        Schema::create('kurikulum', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('judul', 255)->nullable();
            
            // Menggunakan text untuk penjelasan yang agak panjang
            $table->text('penjelasan_kurikulum')->nullable();
            
            // Path file PDF/Gambar dengan komentar sesuai SQL
            $table->string('file_jadwal_path', 255)->nullable()->comment('Path/Nama file PDF/Gambar Jadwal Pelajaran Umum');
            
            // is_active tinyint(1) NOT NULL DEFAULT '1'
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kurikulum');
    }
};