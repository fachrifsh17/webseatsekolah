<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ppdb_link', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('url_link', 255)->nullable();
            $table->enum('status_ppdb', ['Buka','Tutup','Segera'])->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ppdb_link');
    }
};
