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
        Schema::create('album', function (Blueprint $table) {
            // Karena di SQL id Anda adalah varchar(10)
            $table->string('id', 10)->primary(); 
            
            $table->string('nama_album', 255)->nullable();
            $table->date('tanggal_kegiatan')->nullable();
            $table->string('cover_path', 255)->nullable()->comment('Foto sampul album');
            
            // created_at & updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('album');
    }
};