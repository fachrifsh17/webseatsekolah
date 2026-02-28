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
        Schema::create('presensi_siswa_detail', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Foreign Key ke Header (presensi_guru_mapel)
            $table->unsignedBigInteger('presensi_guru_mapel_id');
            
            // Foreign Key ke Siswa (Varchar 255 sesuai SQL Anda)
            $table->string('siswa_id', 255)->nullable();
            
            $table->string('status', 20); // Hadir, Izin, Sakit, Alpa, dll.
            $table->text('catatan')->nullable();
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke Jurnal Mengajar (Header)
            $table->foreign('presensi_guru_mapel_id', 'fk_psd_presensi_header')
                  ->references('id')
                  ->on('presensi_guru_mapel')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Relasi ke Siswa
            $table->foreign('siswa_id', 'fk_detail_ke_siswa_unik')
                  ->references('id')
                  ->on('siswa')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi_siswa_detail');
    }
};