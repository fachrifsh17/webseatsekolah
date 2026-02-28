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
        Schema::create('profil_sekolah', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('nama_sekolah', 150)->nullable();
            $table->text('sejarah')->nullable();
            $table->text('visi')->nullable();
            $table->text('misi')->nullable();
            $table->string('npsn', 20)->nullable();
            $table->string('akreditasi', 10)->nullable();
            
            // Relasi ke Kepala Sekolah (guru_staf)
            $table->string('guru_staf_id', 10)->nullable();
            
            $table->text('sambutan_kepsek')->comment('Teks sambutan Kepala Sekolah');
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('guru_staf_id')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profil_sekolah');
    }
};