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
        Schema::create('orangtua_siswa', function (Blueprint $table) {
            $table->id(); // int AUTO_INCREMENT PRIMARY KEY
            
            // Kolom Foreign Key (Varchar 10)
            $table->string('orangtua_id', 10);
            $table->string('siswa_id', 10);
            
            // Enum hubungan
            $table->enum('hubungan', ['ayah', 'ibu', 'wali'])->default('wali');
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS & INDEXES ---

            // Unique Key agar tidak ada duplikasi hubungan ortu-anak
            $table->unique(['orangtua_id', 'siswa_id'], 'uk_orangtua_siswa');

            // Relasi ke tabel orangtua
            $table->foreign('orangtua_id')
                  ->references('id')
                  ->on('orangtua')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Relasi ke tabel siswa
            $table->foreign('siswa_id')
                  ->references('id')
                  ->on('siswa')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orangtua_siswa');
    }
};