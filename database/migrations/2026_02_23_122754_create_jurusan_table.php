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
        Schema::create('jurusan', function (Blueprint $table) {
            // Primary Key varchar(10)
            $table->string('id', 10)->primary();
            
            $table->string('nama_jurusan', 100)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('foto', 255)->nullable();
            
            // tinyint(1) default 1 diubah menjadi boolean standar Laravel
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurusan');
    }
};