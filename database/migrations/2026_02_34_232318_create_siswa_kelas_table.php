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
        Schema::create('siswa_kelas', function (Blueprint $table) {
            // id bigint UNSIGNED NOT NULL AUTO_INCREMENT
            $table->bigIncrements('id');

            // siswa_id varchar(10) NOT NULL
            $table->string('siswa_id', 10);
            
            // kelas_id varchar(10) NOT NULL
            $table->string('kelas_id', 10);
            
            // tahun_ajaran_id varchar(10) NOT NULL
            $table->string('tahun_ajaran_id', 10);
            
            // is_active tinyint(1) DEFAULT '1'
            $table->boolean('is_active')->default(true);

            // created_at, updated_at
            $table->timestamps();

            // --- Foreign Keys ---
            
            // CONSTRAINT `fk_siswa_history` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
            $table->foreign('siswa_id', 'fk_siswa_history')
                  ->references('id')
                  ->on('siswa')
                  ->onDelete('cascade');

            // CONSTRAINT `fk_kelas_history` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
            $table->foreign('kelas_id', 'fk_kelas_history')
                  ->references('id')
                  ->on('kelas')
                  ->onDelete('cascade');

            // CONSTRAINT `fk_siswa_kelas_tahun_ajaran` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
            $table->foreign('tahun_ajaran_id', 'fk_siswa_kelas_tahun_ajaran')
                  ->references('id')
                  ->on('tahun_ajaran')
                  ->onDelete('restrict')
                  ->onUpdate('cascade');
            
            // --- Indexes ---
            $table->index('is_active', 'idx_sk_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa_kelas');
    }
};