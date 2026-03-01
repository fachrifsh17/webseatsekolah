<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_guru_mapel', function (Blueprint $table) {
            $table->id(); // ID auto-increment (BigInt)

            // guru_mapel menggunakan BigInt (id)
            $table->unsignedBigInteger('guru_mapel_id'); 
            
            // Tabel induk lainnya menggunakan String (VARCHAR 10)
            $table->string('kelas_id', 10)->nullable();
            $table->string('mata_pelajaran_id', 10)->nullable();
            
            // --- PERUBAHAN DI SINI ---
            // Foreign Key diubah ke tabel semesters (bigint)
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            
            $table->date('tanggal');
            $table->string('jam_masuk', 10)->nullable();
            $table->string('jam_keluar', 10)->nullable();
            $table->text('materi')->nullable();
            $table->timestamps();

            // --- Foreign Keys ---
            // Relasi ke guru_mapel menggunakan BigInt
            $table->foreign('guru_mapel_id', 'fk_pgm_gurumapel')
                  ->references('id')->on('guru_mapel')->onDelete('cascade');

            // Relasi lainnya menggunakan String
            $table->foreign('kelas_id', 'fk_pgm_kelas')
                  ->references('id')->on('kelas')->onDelete('cascade');

            $table->foreign('mata_pelajaran_id', 'fk_pgm_matapelajaran')
                  ->references('id')->on('mata_pelajaran')->onDelete('cascade');
            
            $table->foreign('jam_masuk', 'fk_pgm_jammasuk')
                  ->references('id')->on('jam_sekolah')->onDelete('cascade');

            $table->foreign('jam_keluar', 'fk_pgm_jamkeluar')
                  ->references('id')->on('jam_sekolah')->onDelete('cascade');
            
            // --- Indexes (Kunci untuk optimasi query) ---
            $table->index('tanggal', 'idx_presensi_tanggal');
            $table->index(['tanggal', 'kelas_id'], 'idx_presensi_tanggal_kelas');
            $table->index(['kelas_id', 'mata_pelajaran_id'], 'idx_presensi_kelas_mapel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_guru_mapel');
    }
};