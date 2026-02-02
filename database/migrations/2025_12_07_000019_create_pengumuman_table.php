<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->increments('id');
            $table->string('judul', 255)->nullable();
            $table->text('isi_pengumuman')->nullable();
            $table->dateTime('tanggal_publikasi')->nullable();
            $table->tinyInteger('penting')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('pengumuman');
    }
};
