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
        Schema::create('users', function (Blueprint $table) {
            // id varchar(10) PRIMARY KEY
            $table->string('id', 10)->primary();
            
            // Username harus unik sesuai SQL Anda
            $table->string('username', 50)->unique()->comment('Username untuk login');
            
            // Password (laravel menyukai panjang 255 untuk hash)
            $table->string('password', 255)->comment('Disimpan dalam bentuk hash (Wajib Aman)');
            
            $table->string('current_role', 50)->nullable();
            
            // tinyint(1) -> boolean
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};