<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Matikan pengecekan Foreign Key agar MySQL tidak protes soal tabel anak/detail
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 2. Hapus tabel presensi jika ada (supaya bersih)
        Schema::dropIfExists('presensi');

        // 3. Buat tabel presensi
        Schema::create('presensi', function (Blueprint $table) {
            /** * PENTING: Gunakan bigIncrements('id') atau id() 
             * agar menghasilkan BIGINT UNSIGNED. 
             * Ini harus identik dengan presensi_id di tabel presensi_detail.
             */
            $table->id(); 
            
            // Relasi ke Wali Kelas (Harus BIGINT UNSIGNED)
            $table->unsignedBigInteger('kelas_wali_id')->nullable();
            
            $table->date('tanggal');
            $table->timestamps();

            // Index untuk kecepatan query tanggal
            $table->index('tanggal', 'idx_tgl_presensi');
        });

        // 4. Tambahkan Constraint Foreign Key ke kelas_wali_kelas
        Schema::table('presensi', function (Blueprint $table) {
            $table->foreign('kelas_wali_id', 'fk_presensi_ke_wali_kelas')
                  ->references('id')
                  ->on('kelas_wali_kelas')
                  ->onDelete('set null')
                  ->onUpdate('cascade');
        });

        // 5. Hidupkan kembali pengecekan Foreign Key
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};