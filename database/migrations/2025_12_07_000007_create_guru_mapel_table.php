<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('guru_mapel', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('guru_staf_id')->nullable()->index();
            $table->unsignedInteger('mata_pelajaran_id')->nullable()->index();
            $table->foreign('guru_staf_id')->references('id')->on('guru_staf')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('mata_pelajaran_id')->references('id')->on('mata_pelajaran')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('guru_mapel');
    }
};
