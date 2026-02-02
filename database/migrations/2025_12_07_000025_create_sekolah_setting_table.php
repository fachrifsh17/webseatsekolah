<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sekolah_seting', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('tagline', 255)->nullable();
            $table->string('logo', 255)->nullable();
            $table->text('pesan_selamat_datang')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sekolah_seting');
    }
};
