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
        Schema::create('pesan_masuk', function (Blueprint $table) {
            // id int NOT NULL AUTO_INCREMENT PRIMARY KEY
            $table->id(); 
            
            $table->string('nama_lengkap', 100);
            $table->string('email', 100);
            $table->string('subjek', 150)->nullable();
            
            // Isi pesan menggunakan text agar bisa menampung tulisan panjang
            $table->text('isi_pesan');
            
            // Enum untuk status pembacaan
            $table->enum('status', ['belum_dibaca', 'sudah_dibaca'])->default('belum_dibaca');
            
            // tanggal_kirim di SQL menggunakan CURRENT_TIMESTAMP
            // Di Laravel, kita bisa menggunakan timestamps() untuk created_at & updated_at
            $table->timestamp('tanggal_kirim')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pesan_masuk');
    }
};