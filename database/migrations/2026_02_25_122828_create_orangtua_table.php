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
        Schema::create('orangtua', function (Blueprint $table) {
            // id varchar(10) NOT NULL PRIMARY KEY
            $table->string('id', 10)->primary();
            
            // user_id varchar(10) merujuk ke tabel users
            $table->string('user_id', 10)->nullable();
            
            $table->string('nama_lengkap', 100)->nullable();
            $table->string('telepon', 20)->nullable();
            
            // is_active tinyint(1) NOT NULL DEFAULT 1
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            // Menambahkan index untuk status aktif
            $table->index('is_active', 'idx_orangtua_is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orangtua');
    }
};