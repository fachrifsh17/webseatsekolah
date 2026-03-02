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
            $table->integer('id', true);

            $table->string('guru_staf_id', 10)->nullable();
            
            $table->string('kelas_id', 10)->nullable();
            
            $table->date('tanggal');
            
            // Mengubah menjadi unsignedBigInteger agar sesuai dengan tipe data id() di tabel semesters
            $table->unsignedBigInteger('semester_id')->nullable();

            $table->timestamps();

            // --- Foreign Keys ---
            
            $table->foreign('guru_staf_id', 'fk_presensi_guru')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            $table->foreign('kelas_id', 'fk_presensi_kelas')
                  ->references('id')
                  ->on('kelas')
                  ->onDelete('set null')
                  ->onUpdate('cascade');

            // --- PERUBAHAN DI SINI ---
            // CONSTRAINT `fk_presensi_semester` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('semester_id', 'fk_presensi_semester')
                  ->references('id')
                  ->on('semesters')
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