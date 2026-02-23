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
        Schema::create('jabatans', function (Blueprint $table) {
            // id bigint UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('nama_jabatan', 100);
            
            // Slug dibuat unik sesuai dengan SQL Anda
            $table->string('slug', 100)->unique();
            
            $table->text('keterangan')->nullable();
            
            // created_at & updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jabatans');
    }
};