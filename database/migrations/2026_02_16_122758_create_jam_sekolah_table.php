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
            
            // Foreign Key ke tahun_ajaran
            $table->string('tahun_ajaran_id', 10)->nullable();
            
            // Enum untuk hari
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']);
            
            $table->integer('jam_ke')->nullable();
            $table->time('waktu_mulai');
            $table->time('waktu_selesai');
            
            // Enum untuk jenis kegiatan
            $table->enum('jenis', ['Pelajaran', 'Istirahat', 'Kegiatan'])->default('Pelajaran');
            
            $table->string('keterangan', 100)->nullable();
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('tahun_ajaran_id')
                  ->references('id')
                  ->on('tahun_ajaran')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
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