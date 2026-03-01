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
        Schema::create('struktur_jabatan', function (Blueprint $table) {
            $table->id(); // int AUTO_INCREMENT PRIMARY KEY
            
            // Foreign Key ke Guru/Staf (Varchar 10)
            $table->string('guru_staf_id', 10);
            
            // Foreign Key ke tabel jabatans (BigInt Unsigned)
            $table->unsignedBigInteger('jabatan_id')->nullable();
            
            $table->date('periode_mulai')->nullable();
            $table->integer('urutan_tampil')->nullable();
            
            // --- TAMBAHAN KOLOM UNTUK PATH TANDA TANGAN ---
            $table->string('file_ttd', 255)->nullable()->after('urutan_tampil');
            // ----------------------------------------------
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke Guru
            $table->foreign('guru_staf_id', 'fk_struktur_jabatan_guru')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Relasi ke Jabatan
            $table->foreign('jabatan_id', 'fk_struktur_jabatan_role')
                  ->references('id')
                  ->on('jabatans') // Pastikan nama tabelnya 'jabatans'
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('struktur_jabatan');
    }
};