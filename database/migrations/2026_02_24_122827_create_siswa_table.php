<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('user_id', 10)->nullable();
            $table->string('nis', 20)->unique();
            $table->string('nisn', 10)->nullable()->unique();
            $table->string('nik', 16)->nullable()->unique();
            $table->string('nama_lengkap', 100)->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->string('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable();
            $table->string('agama', 20)->nullable();
            $table->year('tahun_angkatan')->nullable();
            $table->string('no_telp_siswa', 15)->nullable();
            $table->text('alamat')->nullable();
            $table->string('foto', 255)->nullable();
            $table->timestamps();
             $table->boolean('is_active')->default(true);

            $table->foreign('user_id', 'fk_siswa_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            
            $table->index('nama_lengkap', 'idx_siswa_nama');
            $table->index('nis', 'idx_siswa_nis');
            $table->index('is_active', 'idx_siswa_status');
            $table->index('tahun_angkatan', 'idx_siswa_angkatan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};