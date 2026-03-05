<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_guru_mapel', function (Blueprint $table) {
            $table->id();

            // 1. Relasi Utama
            $table->foreignId('guru_mapel_id')->constrained('guru_mapel')->onDelete('cascade');
            
            // 2. Kolom Denormalisasi (Nama dikembalikan ke mapel_id)
            $table->unsignedBigInteger('semester_id'); 
            $table->string('guru_staf_id', 10);        
            $table->string('kelas_id', 10);            
            $table->string('mapel_id', 10); // KEMBALI KE NAMA AWAL

            $table->date('tanggal');
            $table->text('materi')->nullable();
            $table->timestamps();

            // 3. Foreign Key Constraints
            $table->foreign('semester_id', 'fk_pgm_semester')
                  ->references('id')->on('semesters')->onDelete('cascade');
            
            $table->foreign('guru_staf_id', 'fk_pgm_guru_staf')
                  ->references('id')->on('guru_staf')->onDelete('cascade');
            
            $table->foreign('kelas_id', 'fk_pgm_kelas')
                  ->references('id')->on('kelas')->onDelete('cascade');
            
            $table->foreign('mapel_id', 'fk_pgm_mapel') // Nama constraint tetap aman
                  ->references('id')->on('mata_pelajaran')->onDelete('cascade');

            // 4. Indexes
            $table->index('tanggal', 'idx_pgm_tanggal');
            $table->index('semester_id', 'idx_pgm_semester');
            $table->index('guru_staf_id', 'idx_pgm_guru');
            $table->index('kelas_id', 'idx_pgm_kelas');
            $table->index('mapel_id', 'idx_pgm_mapel');
            $table->index(['guru_mapel_id', 'tanggal'], 'idx_pgm_guru_tgl'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_guru_mapel');
    }
};