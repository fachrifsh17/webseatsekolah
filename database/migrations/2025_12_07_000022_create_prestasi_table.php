<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('prestasi', function (Blueprint $table) {
            $table->increments('id');
            $table->string('judul', 255)->nullable();
            $table->year('tahun')->nullable();
            $table->string('tingkat', 50)->nullable();
            $table->enum('kategori', ['Siswa','Sekolah'])->nullable();
            $table->string('foto', 255)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('prestasi');
    }
};
