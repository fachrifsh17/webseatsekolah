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
        Schema::create('kelas_wali_kelas', function (Blueprint $table) {
            $table->id();
            
            $table->string('kelas_id', 10);
            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('cascade');
            
            // Kolom diubah menjadi nullable agar tidak error saat create() tanpa guru
            $table->string('guru_staf_id', 10)->nullable(); 
            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')->onDelete('cascade');
            
            // --- PERUBAHAN DI SINI ---
            // Foreign Key diubah ke tabel semesters (bigint)
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('cascade');
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas_wali_kelas');
    }
};