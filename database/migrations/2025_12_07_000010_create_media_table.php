<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('media', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('album_id')->nullable()->index();
            $table->string('media_path', 255)->nullable();
            $table->enum('jenis_media', ['Foto','Video'])->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->foreign('album_id')->references('id')->on('album')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('media');
    }
};
