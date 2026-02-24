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
        // 1. Tabel Users & Akses (Login & Keamanan)
        Schema::table('users', function (Blueprint $table) {
            $table->index('username');
            $table->index('is_active');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->index('role_name');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('role_id');
        });

        // 2. Tabel Presensi & Poin (Optimasi Riwayat)
        Schema::table('presensi_guru_mapel', function (Blueprint $table) { $table->index('tanggal'); });
        Schema::table('presensi_siswa_detail', function (Blueprint $table) { $table->index('status'); });
        Schema::table('presensi', function (Blueprint $table) { $table->index('tanggal'); });
        Schema::table('poin_siswa', function (Blueprint $table) { $table->index('tanggal'); });

        // 3. Tabel Jadwal & Jam Sekolah
        Schema::table('guru_mapel', function (Blueprint $table) { $table->index('hari'); });
        Schema::table('jam_sekolah', function (Blueprint $table) {
            $table->index('hari');
            $table->index('waktu_mulai');
            $table->index('waktu_selesai');
            $table->index('jenis');
        });

        // 4. Tabel Tahun Ajaran
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->index('nama');      
            $table->index('semester');  
            $table->index('is_active'); 
        });

        // 5. Tabel Siswa & Orang Tua
        Schema::table('siswa', function (Blueprint $table) {
            $table->index('nama_lengkap');
            $table->index('is_active');
        });
        Schema::table('orangtua', function (Blueprint $table) {
            $table->index('nama_lengkap');
            $table->index('is_active');
        });

        // 6. Tabel Master Akademik
        Schema::table('jurusan', function (Blueprint $table) { $table->index('is_active'); });

        Schema::table('kelas', function (Blueprint $table) {
            $table->index('nama_kelas');
            $table->index('is_active');
        });

        Schema::table('mata_pelajaran', function (Blueprint $table) {
            $table->index('nama_mapel');
            $table->index('is_active');
            $table->index('tipe_mapel');     
            $table->index('kategori_mapel'); 
        });

        Schema::table('kategori', function (Blueprint $table) {
            $table->index('nama_kategori');
            $table->index('is_active');
        });

        // 7. Tabel Relasi Siswa_Kelas
        Schema::table('siswa_kelas', function (Blueprint $table) { $table->index('is_active'); });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback Users & Roles
        Schema::table('users', function (Blueprint $table) { $table->dropIndex(['username', 'is_active']); });
        Schema::table('roles', function (Blueprint $table) { $table->dropIndex(['role_name']); });
        Schema::table('user_roles', function (Blueprint $table) { $table->dropIndex(['user_id', 'role_id']); });

        // Rollback Presensi
        Schema::table('presensi_guru_mapel', function (Blueprint $table) { $table->dropIndex(['tanggal']); });
        Schema::table('presensi_siswa_detail', function (Blueprint $table) { $table->dropIndex(['status']); });
        Schema::table('presensi', function (Blueprint $table) { $table->dropIndex(['tanggal']); });
        Schema::table('poin_siswa', function (Blueprint $table) { $table->dropIndex(['tanggal']); });
        Schema::table('guru_mapel', function (Blueprint $table) { $table->dropIndex(['hari']); });

        Schema::table('jam_sekolah', function (Blueprint $table) {
            $table->dropIndex(['hari', 'waktu_mulai', 'waktu_selesai', 'jenis']);
        });

        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->dropIndex(['nama', 'semester', 'is_active']);
        });

        Schema::table('siswa', function (Blueprint $table) { $table->dropIndex(['nama_lengkap', 'is_active']); });
        Schema::table('orangtua', function (Blueprint $table) { $table->dropIndex(['nama_lengkap', 'is_active']); });
        Schema::table('jurusan', function (Blueprint $table) { $table->dropIndex(['is_active']); });
        Schema::table('kelas', function (Blueprint $table) { $table->dropIndex(['nama_kelas', 'is_active']); });
        Schema::table('mata_pelajaran', function (Blueprint $table) { $table->dropIndex(['nama_mapel', 'is_active', 'tipe_mapel', 'kategori_mapel']); });
        Schema::table('kategori', function (Blueprint $table) { $table->dropIndex(['nama_kategori', 'is_active']); });
        Schema::table('siswa_kelas', function (Blueprint $table) { $table->dropIndex(['is_active']); });
    }
};