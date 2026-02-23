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
        Schema::create('data_kontak', function (Blueprint $table) {
            // id int NOT NULL PRIMARY KEY
            $table->id(); 
            
            $table->text('alamat_lengkap')->nullable();
            $table->string('telepon', 20)->nullable();
            $table->string('email_resmi', 100)->nullable();
            
            // Menggunakan text untuk menampung iframe/embed code Google Maps
            $table->text('peta_embed_code')->nullable();
            
            // Di SQL Anda hanya ada updated_at, tapi di Laravel standar 
            // menggunakan timestamps() untuk created_at & updated_at.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_kontak');
    }
};