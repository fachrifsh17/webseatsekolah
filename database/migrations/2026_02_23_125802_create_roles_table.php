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
        Schema::create('roles', function (Blueprint $table) {
            // Primary Key varchar(10)
            $table->string('id', 10)->primary();
            
            // Nama Peran dengan komentar sesuai SQL
            $table->string('role_name', 50)->comment('Nama peran (Contoh: Super Admin, Kurikulum, Operator)');
            
            $table->string('description', 255)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};