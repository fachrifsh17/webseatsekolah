<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jurusan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_jurusan', 100)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('foto', 255)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('jurusan');
    }
};
