<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresensiGuruMapel extends Model
{
    protected $table = 'presensi_guru_mapel';

    // Update fillable dengan kolom-kolom optimasi baru
    protected $fillable = [
        'guru_mapel_id',
        'semester_id',
        'guru_staf_id',
        'kelas_id',
        'mapel_id',
        'tanggal',
        'materi',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    // --- RELASI LANGSUNG (CEPAT) ---

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mapel_id');
    }

    // --- RELASI KE JADWAL & DETAIL ---

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class, 'guru_mapel_id');
    }

    public function presensiDetail(): HasMany
    {
        return $this->hasMany(PresensiSiswaDetail::class, 'presensi_guru_mapel_id');
    }

    // --- ACCESSOR (Untuk Tampilan Lebih Bersih) ---

    // Sekarang Anda bisa panggil: $absen->nama_kelas
    public function getNamaKelasAttribute()
    {
        return $this->kelas->nama_kelas ?? '-';
    }

    // Sekarang Anda bisa panggil: $absen->nama_mapel
    public function getNamaMapelAttribute()
    {
        return $this->mapel->nama_mapel ?? '-';
    }
}