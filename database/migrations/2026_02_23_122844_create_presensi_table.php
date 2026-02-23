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
        Schema::create('presensi', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Foreign Keys (Varchar 10 sesuai database Anda)
            $table->string('siswa_id', 10)->nullable();
            $table->string('guru_staf_id', 10)->nullable();
            $table->string('tahun_ajaran_id', 10)->nullable();
            
            $table->date('tanggal');
            
            // Enum Status Kehadiran
            $table->enum('status', ['Hadir', 'Izin', 'Sakit', 'Alpa']);
            
            $table->text('keterangan')->nullable();
            
            // created_at & updated_at
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke Siswa (Jika siswa dihapus, data presensi ikut terhapus)
            $table->foreign('siswa_id')->references('id')->on('siswa')
                  ->onDelete('cascade')->onUpdate('cascade');

            // Relasi ke Guru (Jika guru dihapus, set NULL agar history tetap ada)
            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')
                  ->onDelete('set null')->onUpdate('cascade');

            // Relasi ke Tahun Ajaran
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')
                  ->onDelete('set null')->onUpdate('cascade');

            // Indexing untuk mempercepat filter laporan absen berdasarkan tanggal
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};