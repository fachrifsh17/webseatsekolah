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
        Schema::create('kalender_akademik', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Foreign Key ke tahun_ajaran (varchar 10)
            $table->string('tahun_ajaran_id', 10)->nullable();
            
            $table->string('kegiatan', 255)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            
            // Enum kategori sesuai SQL Anda
            $table->enum('kategori', ['Ujian', 'Libur', 'Hari Efektif', 'Akademik'])->nullable();
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('tahun_ajaran_id')
                  ->references('id')
                  ->on('tahun_ajaran')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kalender_akademik');
    }
};