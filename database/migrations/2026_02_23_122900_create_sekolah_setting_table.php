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
        Schema::create('sekolah_setting', function (Blueprint $table) {
            // id int NOT NULL PRIMARY KEY
            $table->id(); 
            
            $table->string('tagline', 255)->nullable();
            
            // Path logo sekolah
            $table->string('logo', 255)->nullable()->comment('Nama file atau path logo');
            
            $table->text('pesan_selamat_datang')->nullable();
            
            // Link/Path dokumen buku poin
            $table->string('buku_poin_path', 255)->nullable()->comment('Path file buku poin siswa');
            
            // Kontak kesiswaan
            $table->string('no_wa_kesiswaan', 20)->nullable()->comment('Nomor WhatsApp kesiswaan');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sekolah_setting');
    }
};