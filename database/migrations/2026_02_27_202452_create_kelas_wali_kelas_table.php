<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_wali_kelas', function (Blueprint $table) {
            $table->id();
            
            // Foreign Key ke tabel kelas
            $table->string('kelas_id', 10);
            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('cascade');
            
            // Foreign Key ke tabel guru_staf
            $table->string('guru_staf_id', 10);
            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')->onDelete('cascade');
            
            // Foreign Key ke tabel tahun_ajaran
            $table->string('tahun_ajaran_id', 10);
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->onDelete('cascade');
            
            // Status aktif relasi pada semester berjalan
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_wali_kelas');
    }
};