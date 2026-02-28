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
            // id varchar(10) NOT NULL
            $table->string('id', 10)->primary();

            // user_id varchar(10) DEFAULT NULL
            $table->string('user_id', 10)->nullable();
            
            // nis varchar(20) NOT NULL (Unique)
            $table->string('nis', 20)->unique();
            
            // nisn varchar(10) DEFAULT NULL (Unique)
            $table->string('nisn', 10)->nullable()->unique();
            
            // nama_lengkap varchar(100)
            $table->string('nama_lengkap', 100)->nullable();
            
            // tempat_lahir varchar(100)
            $table->string('tempat_lahir', 100)->nullable();
            
            // tanggal_lahir date
            $table->date('tanggal_lahir')->nullable();
            
            // jenis_kelamin enum('Laki-laki','Perempuan')
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable();
            
            // is_active tinyint(1) NOT NULL DEFAULT '1'
            $table->boolean('is_active')->default(true);
            
            // foto varchar(255)
            $table->string('foto', 255)->nullable();
            
            // no_telp_siswa varchar(15)
            $table->string('no_telp_siswa', 15)->nullable();
            
            // alamat text
            $table->text('alamat')->nullable();

            // created_at, updated_at
            $table->timestamps();

            // --- Foreign Keys ---
            
            // CONSTRAINT `fk_siswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            $table->foreign('user_id', 'fk_siswa_user')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            
            // --- Indexes ---
            $table->index('nama_lengkap', 'idx_siswa_nama');
            $table->index('nis', 'idx_siswa_nis');
            $table->index('is_active', 'idx_siswa_status');
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