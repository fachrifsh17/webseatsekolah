<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// Hapus import Kelas, MataPelajaran, Semester, JamSekolah jika tidak digunakan di model lain

class PresensiGuruMapel extends Model
{
    protected $table = 'presensi_guru_mapel';

    protected $fillable = [
        'guru_mapel_id',
        'tanggal',
        'materi',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // Relasi utama ke penugasan guru
    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class, 'guru_mapel_id', 'id');
    }

    // Detail kehadiran siswa
    public function presensiDetail(): HasMany
    {
        return $this->hasMany(PresensiSiswaDetail::class, 'presensi_guru_mapel_id', 'id');
    }

    // --- FUNGSI HELPER (Opsional tapi disarankan) ---
    // Fungsi ini memudahkan mengambil data kelas/mapel tanpa harus menulis $model->guruMapel->kelas
    
    public function getKelasNameAttribute()
    {
        return $this->guruMapel->kelas->nama_kelas; // Sesuaikan dengan nama kolom di model Kelas
    }

    public function getMapelNameAttribute()
    {
        return $this->guruMapel->mapel->nama_mapel; // Sesuaikan dengan nama kolom di model MataPelajaran
    }
}