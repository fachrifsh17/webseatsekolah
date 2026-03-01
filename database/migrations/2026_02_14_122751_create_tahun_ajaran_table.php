<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            
            // Kolom kurikulum_id dipertahankan sesuai permintaan
            $table->unsignedBigInteger('kurikulum_id')->nullable();
            
            $table->string('nama', 20); // Contoh: 2025/2026
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // Constraint Foreign Key ke kurikulum
            $table->foreign('kurikulum_id', 'fk_tahun_ajaran_kurikulum')
                  ->references('id')
                  ->on('kurikulum')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahun_ajaran');
    }
};