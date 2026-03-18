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
        Schema::create('ekstrakurikuler', function (Blueprint $table) {
            // PK utama
            $table->id(); 
            
            $table->string('nama_ekskul', 100)->nullable();
            $table->text('deskripsi')->nullable();
            
            // Kolom hari untuk menyimpan range atau list hari (misal: Senin - Rabu)
            $table->string('hari', 100)->nullable();
            
            // Kolom Foreign Key: Harus string(10) karena merujuk ke guru_staf.id
            $table->string('pembina_id', 10)->nullable();
            
            $table->string('foto', 255)->nullable()->comment('Foto kegiatan ekskul');
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();

            // SETTING FOREIGN KEY
            $table->foreign('pembina_id')
                  ->references('id')
                  ->on('guru_staf')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ekstrakurikuler');
    }
};