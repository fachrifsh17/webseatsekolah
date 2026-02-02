<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ekstrakurikuler', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_ekskul', 100)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('hari', 50)->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->unsignedInteger('pembina_id')->nullable()->index();
            $table->string('foto', 255)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->foreign('pembina_id')->references('id')->on('guru_staf')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ekstrakurikuler');
    }
};
