<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('guru_staf', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nip', 18)->nullable()->unique();
            $table->string('nuptk', 16)->nullable()->unique();
            $table->string('nama', 100)->nullable();
            $table->string('jabatan_fungsional', 100)->nullable();
            $table->string('status_kepegawaian', 50)->nullable();
            $table->string('foto', 255)->nullable();
            $table->unsignedInteger('jurusan_id')->nullable()->index();
            $table->foreign('jurusan_id')->references('id')->on('jurusan')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('guru_staf');
    }
};
