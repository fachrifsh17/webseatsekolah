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
        Schema::create('presensi_guru_mapel', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Foreign Key ke guru_mapel (integer)
            $table->unsignedBigInteger('guru_mapel_id');
            
            // Kolom referensi (Varchar sesuai SQL)
            $table->string('kelas_id', 50)->nullable();
            $table->string('mata_pelajaran_id', 50)->nullable();
            $table->string('tahun_ajaran_id', 10)->nullable();
            
            $table->date('tanggal');
            $table->string('jam_masuk', 10)->nullable();
            $table->string('jam_keluar', 10)->nullable();
            $table->text('materi')->nullable();
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke guru_mapel
            $table->foreign('guru_mapel_id')
                  ->references('id')
                  ->on('guru_mapel')
                  ->onDelete('cascade');

            // Relasi ke tahun_ajaran
            $table->foreign('tahun_ajaran_id')
                  ->references('id')
                  ->on('tahun_ajaran')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi_guru_mapel');
    }
};