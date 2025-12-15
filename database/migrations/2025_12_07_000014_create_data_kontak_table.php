<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('data_kontak', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->text('alamat_lengkap')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('email_resmi', 100)->nullable();
            $table->text('peta_embed_code')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('data_kontak');
    }
};
