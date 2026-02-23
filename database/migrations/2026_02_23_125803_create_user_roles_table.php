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
        Schema::create('user_roles', function (Blueprint $table) {
            // Foreign Key dari tabel users (varchar 10)
            $table->string('user_id', 10);
            
            // Foreign Key dari tabel roles (varchar 10)
            $table->string('role_id', 10);
            
            $table->timestamps();

            // Menetapkan Composite Primary Key (user_id + role_id)
            // Agar tidak ada user yang memiliki role yang sama dua kali
            $table->primary(['user_id', 'role_id']);

            // --- SETTING CONSTRAINTS ---
            $table->foreign('user_id', 'fk_to_users')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');

            $table->foreign('role_id', 'fk_to_roles')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};