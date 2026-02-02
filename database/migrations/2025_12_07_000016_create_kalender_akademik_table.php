<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kalender_akademik', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kegiatan', 255)->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->enum('kategori', ['Ujian','Libur','Hari Efektif'])->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('kalender_akademik');
    }
};
