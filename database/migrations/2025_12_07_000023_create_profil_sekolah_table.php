<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profil_sekolah', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->text('sejarah')->nullable();
            $table->text('visi')->nullable();
            $table->text('misi')->nullable();
            $table->string('npsn', 20)->nullable();
            $table->string('akreditasi', 10)->nullable();
            $table->text('sambutan_kepsek')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profil_sekolah');
    }
};
