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
        Schema::create('auth_tokens', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Menggunakan collation unicode sesuai SQL asli
            $table->string('token_hash', 255)->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            $table->string('refresh_token', 64)->nullable()->unique()->charset('utf8mb4')->collation('utf8mb4_unicode_ci');
            
            // Relasi ke tabel users (Varchar 10)
            $table->string('user_id', 10);
            
            $table->dateTime('expires_at');
            $table->timestamp('refresh_expires_at')->nullable();
            
            // tinyint(1) dipetakan ke boolean
            $table->boolean('revoked')->default(false);
            
            // created_at & updated_at
            $table->dateTime('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            // --- INDEX & CONSTRAINTS ---
            $table->index('token_hash', 'idx_auth_token_hash');
            $table->index('user_id', 'idx_auth_user');

            $table->foreign('user_id', 'fk_auth_tokens_user')
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
        Schema::dropIfExists('auth_tokens');
    }
};