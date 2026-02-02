<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('album', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_album', 255)->nullable();
            $table->date('tanggal_kegiatan')->nullable();
            $table->string('cover_path', 255)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('album');
    }
};
