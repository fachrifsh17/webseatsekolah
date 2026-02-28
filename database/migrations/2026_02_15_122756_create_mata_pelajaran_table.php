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
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            // Primary Key varchar(10)
            $table->string('id', 10)->primary();
            
            $table->string('nama_mapel', 100)->nullable();
            
            // Foreign Key ke Jurusan (varchar 10)
            $table->string('jurusan_id', 10)->nullable();
            
            // Enum Tipe Mapel
            $table->enum('tipe_mapel', ['umum', 'khusus'])->default('umum');
            
            // Enum Kategori Mapel
            $table->enum('kategori_mapel', ['normatif', 'adaptif', 'produktif'])->default('adaptif');
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('jurusan_id')
                  ->references('id')
                  ->on('jurusan')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};