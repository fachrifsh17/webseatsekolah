<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kurikulum', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('judul', 255)->nullable();
            $table->text('penjelasan_kurikulum')->nullable();
            $table->string('file_jadwal_path', 255)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('kurikulum');
    }
};
