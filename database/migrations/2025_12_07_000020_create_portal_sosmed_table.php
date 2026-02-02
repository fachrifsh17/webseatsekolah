<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('portal_sosmed', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_platform', 100)->nullable();
            $table->string('url_link', 255)->nullable();
            $table->enum('tipe', ['Sosial Media','Portal Khusus'])->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('portal_sosmed');
    }
};
