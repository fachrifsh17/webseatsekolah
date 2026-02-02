<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fasilitas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_fasilitas', 150)->nullable();
            $table->string('foto', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('fasilitas');
    }
};
