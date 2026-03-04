<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::dropIfExists('presensi');

        Schema::create('presensi', function (Blueprint $table) {
            $table->id(); 
            
            $table->foreignId('semester_id')->nullable();
            $table->string('kelas_id', 10)->nullable();
            $table->unsignedBigInteger('kelas_wali_id')->nullable();
            $table->string('guru_id', 10)->nullable(); // Panjang disamakan (10)
            
            $table->date('tanggal');
            $table->timestamps();

            $table->index(['tanggal', 'semester_id', 'kelas_id'], 'idx_laporan_presensi');
        });

        Schema::table('presensi', function (Blueprint $table) {
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('kelas_wali_id', 'fk_presensi_ke_wali_kelas')->references('id')->on('kelas_wali_kelas')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('guru_id')->references('id')->on('guru_staf')->onDelete('set null')->onUpdate('cascade');
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};