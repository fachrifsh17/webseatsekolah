<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poin_siswa', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('siswa_id', 10);
            $table->string('guru_staf_id', 10)->nullable();
            $table->string('kelas_id', 10)->nullable();
            $table->date('tanggal');
            $table->text('indikator');
            $table->integer('poin_positif')->default(0);
            $table->integer('poin_negatif')->default(0);
            
            // Kolom diubah menjadi unsignedBigInteger agar sesuai dengan semesters.id
            $table->unsignedBigInteger('semester_id')->nullable();

            $table->timestamps();

            $table->foreign('siswa_id', 'fk_poin_siswa')
                  ->references('id')
                  ->on('siswa')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('guru_staf_id', 'fk_poin_guru')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->foreign('kelas_id', 'fk_poin_siswa_kelas')
                  ->references('id')
                  ->on('kelas')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->foreign('semester_id', 'fk_poin_semester')
                  ->references('id')
                  ->on('semesters')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
            
            $table->index('tanggal', 'tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poin_siswa');
    }
};