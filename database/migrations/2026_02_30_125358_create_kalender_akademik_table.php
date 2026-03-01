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
        Schema::create('kalender_akademik', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // --- PERUBAHAN DI SINI ---
            // Foreign Key diubah ke tabel semesters (bigint)
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('cascade');
            
            $table->string('kegiatan', 255)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            
            // Enum kategori sesuai SQL Anda
            $table->enum('kategori', ['Ujian', 'Libur', 'Hari Efektif', 'Akademik'])->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kalender_akademik');
    }
};