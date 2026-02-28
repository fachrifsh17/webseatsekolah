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
        Schema::create('media', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            // Foreign Key ke album (varchar 10)
            $table->string('album_id', 10)->nullable();
            
            $table->string('media_path', 255)->nullable()->comment('Nama file (Foto) atau URL Embed (Video)');
            
            // Enum jenis media
            $table->enum('jenis_media', ['Foto', 'Video'])->nullable();
            
            $table->string('keterangan', 255)->nullable();
            
            $table->timestamps();

            // --- SETTING CONSTRAINTS ---
            $table->foreign('album_id')
                  ->references('id')
                  ->on('album')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};