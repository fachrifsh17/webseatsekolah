<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('struktur_jabatan', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('guru_staf_id')->nullable()->index();
            $table->string('nama_jabatan_struktural', 100)->nullable();
            $table->date('periode_mulai')->nullable();
            $table->integer('urutan_tampil')->nullable();
            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('struktur_jabatan');
    }
};
