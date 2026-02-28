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
            // id int NOT NULL AUTO_INCREMENT
            $table->integer('id', true); // true = autoIncrement

            // siswa_id varchar(10) NOT NULL
            $table->string('siswa_id', 10);
            
            // guru_staf_id varchar(10) DEFAULT NULL
            $table->string('guru_staf_id', 10)->nullable();
            
            // kelas_id varchar(10) DEFAULT NULL
            $table->string('kelas_id', 10)->nullable();
            
            // tanggal date NOT NULL
            $table->date('tanggal');
            
            // indikator text NOT NULL
            $table->text('indikator');
            
            // poin_positif int DEFAULT '0'
            $table->integer('poin_positif')->default(0);
            
            // poin_negatif int DEFAULT '0'
            $table->integer('poin_negatif')->default(0);
            
            // tahun_ajaran_id varchar(10) DEFAULT NULL
            $table->string('tahun_ajaran_id', 10)->nullable();

            // created_at, updated_at
            $table->timestamps();

            // --- Foreign Keys ---
            
            // CONSTRAINT `fk_poin_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            $table->foreign('siswa_id', 'fk_poin_siswa')
                  ->references('id')
                  ->on('siswa')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // CONSTRAINT `fk_poin_guru` FOREIGN KEY (`guru_staf_id`) REFERENCES `guru_staf` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('guru_staf_id', 'fk_poin_guru')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            // CONSTRAINT `fk_poin_siswa_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('kelas_id', 'fk_poin_siswa_kelas')
                  ->references('id')
                  ->on('kelas')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            // CONSTRAINT `fk_poin_tahun_ajaran` FOREIGN KEY (`tahun_ajaran_id`) REFERENCES `tahun_ajaran` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('tahun_ajaran_id', 'fk_poin_tahun_ajaran')
                  ->references('id')
                  ->on('tahun_ajaran')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
            
            // --- Indexes ---
            $table->index('tanggal', 'tanggal');
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