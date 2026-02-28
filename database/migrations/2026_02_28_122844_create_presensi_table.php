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
        Schema::create('presensi', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT
            $table->integer('id', true); // true = autoIncrement

            // guru_staf_id varchar(10) DEFAULT NULL
            $table->string('guru_staf_id', 10)->nullable();
            
            // kelas_id varchar(10) DEFAULT NULL
            $table->string('kelas_id', 10)->nullable();
            
            // tanggal date NOT NULL
            $table->date('tanggal');
            
            // tahun_ajaran_id varchar(10) DEFAULT NULL
            $table->string('tahun_ajaran_id', 10)->nullable();

            // created_at, updated_at
            $table->timestamps();

            // --- Foreign Keys ---
            
            // CONSTRAINT `fk_presensi_guru` FOREIGN KEY (`guru_staf_id`) REFERENCES `guru_staf` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('guru_staf_id', 'fk_presensi_guru')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            // CONSTRAINT `fk_presensi_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('kelas_id', 'fk_presensi_kelas')
                  ->references('id')
                  ->on('kelas')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            // CONSTRAINT `fk_presensi_tahun_ajaran` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('tahun_ajaran_id', 'fk_presensi_tahun_ajaran')
                  ->references('id')
                  ->on('tahun_ajaran')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
            
            // --- Indexes ---
            $table->index('tanggal', 'tanggal');
            $table->index('tanggal', 'tanggal_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};