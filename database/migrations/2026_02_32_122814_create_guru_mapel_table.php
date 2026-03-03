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
        Schema::create('guru_mapel', function (Blueprint $table) {
            $table->id();

            $table->string('guru_staf_id', 10);
            $table->string('mata_pelajaran_id', 10);
            $table->string('kelas_id', 10);
            
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('set null');
            
            $table->enum('hari', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'])->nullable();
            
            $table->string('jam_mulai_id', 10)->nullable();
            $table->string('jam_selesai_id', 10)->nullable();
            
            // --- PERUBAHAN DI SINI ---
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')
                  ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')
                  ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('kelas_id')->references('id')->on('kelas')
                  ->onDelete('cascade')->onUpdate('cascade');

            $table->foreign('jam_mulai_id')->references('id')->on('jam_sekolah')
                  ->onDelete('set null')->onUpdate('cascade');
            $table->foreign('jam_selesai_id')->references('id')->on('jam_sekolah')
                  ->onDelete('set null')->onUpdate('cascade');

            $table->index(['semester_id', 'hari'], 'idx_gm_semester_hari');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_mapel');
    }
};