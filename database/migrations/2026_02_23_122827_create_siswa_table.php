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
        Schema::create('siswa', function (Blueprint $table) {
            // Primary Key varchar(10)
            $table->string('id', 10)->primary();
            
            // Foreign Keys
            $table->string('user_id', 10)->nullable();
            $table->string('kelas_id', 10)->nullable();
            
            // Identitas Unik
            $table->string('nis', 20)->unique();
            $table->string('nisn', 10)->nullable()->unique();
            
            $table->string('nama_lengkap', 100)->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('foto', 255)->nullable();
            $table->string('no_telp_siswa', 15)->nullable();
            $table->text('alamat')->nullable();
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---

            // Relasi ke tabel users
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Relasi ke tabel kelas
            $table->foreign('kelas_id')
                  ->references('id')
                  ->on('kelas')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};