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
        Schema::create('guru_staf', function (Blueprint $table) {
            // Primary Key menggunakan string(10) sesuai SQL
            $table->string('id', 10)->primary();
            
            // Foreign Key ke tabel users
            $table->string('user_id', 10)->nullable();
            
            // Data Identitas dengan Unique Constraint
            $table->string('nip', 18)->unique()->nullable()->comment('NIP PNS/PPPK (Bisa NULL)');
            $table->string('nuptk', 16)->unique()->nullable()->comment('NUPTK Pendidik/Tendik (Bisa NULL)');
            
            $table->string('nama', 100)->nullable();
            $table->string('jabatan_fungsional', 100)->nullable()->comment('Contoh: Guru, Laboran, Staf TU');
            $table->string('status_kepegawaian', 50)->nullable()->comment('PNS, Honorer, Yayasan');
            $table->string('foto', 255)->nullable()->comment('Path/Nama file foto Guru/Staf');
            
            // Relasi ke Jurusan
            $table->string('jurusan_id', 10)->nullable();
            
            // Status Aktif (boolean)
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke tabel jurusan
            $table->foreign('jurusan_id')->references('id')->on('jurusan')
                  ->onDelete('set null')->onUpdate('cascade');

            // Relasi ke tabel users
            $table->foreign('user_id')->references('id')->on('users')
                  ->onDelete('set null')->onUpdate('cascade');

            // Indexing untuk pencarian cepat
            $table->index('is_active', 'idx_guru_staff_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_staf');
    }
};