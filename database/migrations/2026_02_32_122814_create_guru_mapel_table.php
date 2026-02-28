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
        Schema::create('guru_mapel', function (Blueprint $table) {
            $table->id(); // Primary Key (int AUTO_INCREMENT)

            // Kolom-kolom Foreign Key (Varchar 10 sesuai SQL)
            $table->string('guru_staf_id', 10);
            $table->string('mata_pelajaran_id', 10);
            $table->string('kelas_id', 10);
            $table->string('tahun_ajaran_id', 10)->nullable();
            
            // Enum untuk hari
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'])->nullable();
            
            $table->string('jam_mulai_id', 10)->nullable();
            $table->string('jam_selesai_id', 10)->nullable();
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS (Hubungan Antar Tabel) ---

            // Relasi ke tabel guru_staf
            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')
                  ->onDelete('cascade')->onUpdate('cascade');

            // Relasi ke tabel mata_pelajaran
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')
                  ->onDelete('cascade')->onUpdate('cascade');

            // Relasi ke tabel kelas
            $table->foreign('kelas_id')->references('id')->on('kelas')
                  ->onDelete('cascade')->onUpdate('cascade');

            // Relasi ke tabel tahun_ajaran
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')
                  ->onDelete('set null')->onUpdate('cascade');

            // Relasi ke tabel jam_sekolah (mulai & selesai)
            $table->foreign('jam_mulai_id')->references('id')->on('jam_sekolah')
                  ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('jam_selesai_id')->references('id')->on('jam_sekolah')
                  ->onDelete('set null')->onUpdate('cascade');

            // Menambahkan Index tambahan sesuai SQL Anda
            $table->index(['tahun_ajaran_id', 'hari'], 'idx_gm_ta_hari');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_mapel');
    }
};