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
        Schema::create('kelas', function (Blueprint $table) {
            // Primary Key varchar(10)
            $table->string('id', 10)->primary();
            
            $table->string('nama_kelas', 50)->nullable();
            
            // Kolom Foreign Keys (semuanya varchar 10 sesuai SQL)
            $table->string('jurusan_id', 10)->nullable();
            $table->string('wali_kelas_id', 10)->nullable();
            $table->string('tahun_ajaran_id', 10)->nullable();
            
            // tinyint(1) default 1
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke Jurusan
            $table->foreign('jurusan_id')->references('id')->on('jurusan')
                  ->onDelete('set null')->onUpdate('cascade');

            // Relasi ke Tahun Ajaran
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')
                  ->onDelete('set null')->onUpdate('cascade');

            // Relasi ke Guru Staf (Wali Kelas)
            $table->foreign('wali_kelas_id')->references('id')->on('guru_staf')
                  ->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};