<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('berita', function (Blueprint $table) {
            $table->increments('id');
            $table->string('judul', 255)->nullable();
            $table->longText('isi_berita')->nullable();
            $table->dateTime('tanggal_publikasi')->nullable();
            $table->string('foto', 255)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('berita');
    }
};
