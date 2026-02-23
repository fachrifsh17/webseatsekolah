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
        Schema::create('log_aktivitas', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Foreign Key ke users (Varchar 10 sesuai SQL)
            $table->string('user_id', 10);
            
            $table->string('aksi', 255);
            $table->string('ip_address', 45)->nullable();
            
            // User agent biasanya panjang, maka gunakan text
            $table->text('user_agent')->nullable();
            
            // created_at & updated_at
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};