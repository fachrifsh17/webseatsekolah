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
        Schema::create('presensi_detail', function (Blueprint $table) {
            // id bigint UNSIGNED NOT NULL AUTO_INCREMENT
            $table->id();

            // PERBAIKAN: Harus unsignedBigInteger agar cocok dengan id() di tabel presensi
            $table->unsignedBigInteger('presensi_id');
            
            // siswa_id varchar(10) NOT NULL
            $table->string('siswa_id', 10);
            
            // status enum('Hadir','Izin','Sakit','Alpa') NOT NULL
            $table->enum('status', ['Hadir', 'Izin', 'Sakit', 'Alpa']);
            
            // keterangan text
            $table->text('keterangan')->nullable();

            // created_at, updated_at
            $table->timestamps();

            // --- Foreign Keys ---
            
            // Menghubungkan ke tabel presensi (Header)
            $table->foreign('presensi_id', 'fk_detail_to_header')
                  ->references('id')
                  ->on('presensi')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Menghubungkan ke tabel siswa
            $table->foreign('siswa_id', 'fk_detail_to_siswa')
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
        Schema::dropIfExists('presensi_detail');
    }
};