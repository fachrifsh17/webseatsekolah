<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_siswa_detail', function (Blueprint $table) {
            $table->id(); 
            
            // Menggunakan foreignId jauh lebih aman untuk menghindari error "incompatible"
            $table->foreignId('presensi_guru_mapel_id')
                  ->constrained('presensi_guru_mapel')
                  ->onDelete('cascade')
                  ->name('fk_psd_header'); // Nama kustom agar tidak kepanjangan
            
            // Sesuaikan siswa_id dengan tipe data di tabel 'siswa'
            // Jika di tabel siswa ID-nya string (NISN), gunakan ini:
            $table->string('siswa_id', 255); 
            
            $table->enum('status', ['Hadir', 'Izin', 'Sakit', 'Alpa', 'Dispen'])->default('Hadir');
            $table->text('catatan')->nullable();
            
            $table->timestamps();

            // Setting Foreign Key ke Siswa
            $table->foreign('siswa_id', 'fk_psd_siswa')
                  ->references('id')
                  ->on('siswa') // Pastikan nama tabelnya 'siswa' bukan 'siswas'
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Index untuk mempercepat query laporan per siswa
            $table->index('status');
            $table->index('siswa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_siswa_detail');
    }
};