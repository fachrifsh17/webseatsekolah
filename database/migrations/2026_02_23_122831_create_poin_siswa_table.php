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
        Schema::create('poin_siswa', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Foreign Keys (Varchar 10 sesuai database lama)
            $table->string('siswa_id', 10);
            $table->string('guru_staf_id', 10)->nullable();
            $table->string('tahun_ajaran_id', 10)->nullable();
            
            $table->date('tanggal');
            $table->text('indikator');
            $table->integer('poin_positif')->default(0);
            $table->integer('poin_negatif')->default(0);
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke Siswa
            $table->foreign('siswa_id')->references('id')->on('siswa')
                  ->onDelete('cascade')->onUpdate('cascade');

            // Relasi ke Guru
            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')
                  ->onDelete('set null')->onUpdate('cascade');

            // Relasi ke Tahun Ajaran
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')
                  ->onDelete('set null')->onUpdate('cascade');

            // Indexing untuk filter pencarian berdasarkan tanggal
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poin_siswa');
    }
};