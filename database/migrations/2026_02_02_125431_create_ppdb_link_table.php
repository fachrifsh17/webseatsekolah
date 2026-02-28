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
        Schema::create('ppdb_link', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('url_link', 255)->nullable()->comment('Link ke sistem PPDB eksternal');
            
            // Enum status pendaftaran
            $table->enum('status_ppdb', ['Buka', 'Tutup', 'Segera'])->nullable();
            
            // Menggunakan timestamps standar Laravel
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_link');
    }
};