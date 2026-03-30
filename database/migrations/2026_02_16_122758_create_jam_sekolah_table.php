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
        Schema::create('jam_sekolah', function (Blueprint $table) {
            // Primary Key varchar(10)
            $table->string('id', 10)->primary();
            
            // --- PERUBAHAN DI SINI ---
            // Foreign Key diubah ke tabel semesters (bigint)
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('cascade');
            
            // Enum untuk hari
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']);
            
            $table->integer('jam_ke')->nullable();
            $table->time('waktu_mulai');
            $table->time('waktu_selesai');
            
            // Enum untuk jenis kegiatan
            $table->enum('jenis', ['Pelajaran', 'Istirahat', 'Kegiatan'])->default('Pelajaran');
            
            $table->string('keterangan', 100)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jam_sekolah');
    }
};