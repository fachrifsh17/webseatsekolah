<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            // Primary Key varchar(10)
            $table->string('id', 10)->primary();
            
            // Foreign Key ke kurikulum (int)
            $table->unsignedBigInteger('kurikulum_id')->nullable();
            
            $table->string('nama', 20); // Contoh: 2025/2026
            $table->enum('semester', ['Ganjil', 'Genap'])->default('Ganjil');
            
            // tinyint(1) dipetakan ke boolean
            $table->boolean('is_active')->default(false);
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('kurikulum_id', 'fk_tahun_ajaran_kurikulum')
                  ->references('id')
                  ->on('kurikulum')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tahun_ajaran');
    }
};