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
        Schema::create('kelas', function (Blueprint $table) {
            // id varchar(10) NOT NULL
            $table->string('id', 10)->primary();

            // nama_kelas varchar(50) DEFAULT NULL
            $table->string('nama_kelas', 50)->nullable();
            
            // jurusan_id varchar(10) DEFAULT NULL
            $table->string('jurusan_id', 10)->nullable();
            
            // --- PERUBAHAN DI SINI ---
            // tingkatan_id diubah menjadi integer agar sesuai dengan tipe data di tabel 'tingkatan'
            $table->unsignedInteger('tingkatan_id')->nullable();

            // is_active tinyint(1) NOT NULL DEFAULT '1'
            $table->boolean('is_active')->default(true);

            // created_at, updated_at timestamp
            $table->timestamps();

            // --- Foreign Keys ---
            
            // CONSTRAINT `fk_kelas_jurusan` FOREIGN KEY (`jurusan_id`) REFERENCES `jurusan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            $table->foreign('jurusan_id', 'fk_kelas_jurusan')
                  ->references('id')
                  ->on('jurusan')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
            
            // --- PERUBAHAN DI SINI ---
            // Foreign Key ke tabel tingkatan (referencing to integer id)
            $table->foreign('tingkatan_id', 'fk_kelas_tingkatan')
                  ->references('id')
                  ->on('tingkatan')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
            
            // --- Indexes ---
            $table->index('nama_kelas', 'idx_kelas_nama');
            $table->index('is_active', 'idx_kelas_status');
            $table->index('tingkatan_id', 'idx_kelas_tingkatan'); // Index untuk tingkatan_id
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};