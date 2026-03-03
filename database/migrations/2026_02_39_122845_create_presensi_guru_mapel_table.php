<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_guru_mapel', function (Blueprint $table) {
            $table->id();

            // Rujukan utama: Guru mana, mengajar apa, di kelas apa, semester apa, jam berapa
            $table->foreignId('guru_mapel_id')->constrained('guru_mapel')->onDelete('cascade');
            
            $table->date('tanggal');
            $table->text('materi')->nullable();
            $table->timestamps();

            // --- Indexes (Kunci untuk optimasi query) ---
            $table->index('tanggal', 'idx_presensi_tanggal');
            // Index untuk mempercepat pencarian berdasarkan guru dan tanggal
            $table->index(['guru_mapel_id', 'tanggal'], 'idx_guru_tanggal'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_guru_mapel');
    }
};