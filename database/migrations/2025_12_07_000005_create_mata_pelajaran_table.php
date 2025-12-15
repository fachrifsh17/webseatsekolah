<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_mapel', 20)->unique(); // kode unik, misalnya MTK01
            $table->string('nama_mapel', 100);          // nama pelajaran, misalnya Matematika
            $table->string('kelompok', 50)->nullable(); // kelompok mapel (IPA, IPS, Wajib, Pilihan)
            $table->integer('jam_per_minggu')->default(2); // jumlah jam per minggu
            $table->unsignedInteger('guru_id')->nullable(); // relasi ke guru pengampu
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // relasi ke tabel guru (opsional, jika ada tabel guru)
            $table->foreign('guru_id')
                  ->references('id')
                  ->on('guru')
                  ->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::dropIfExists('mata_pelajaran');
    }
};
