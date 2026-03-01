<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PresensiGuruMapel extends Model
{
    protected $table = 'presensi_guru_mapel';

    protected $fillable = [
        'guru_mapel_id',
        'kelas_id',
        'mata_pelajaran_id',
        'semester_id', // --- PERUBAHAN DI SINI ---
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'materi',
    ];

    protected $casts = [
        'semester_id' => 'string', // --- PERUBAHAN DI SINI ---
        'tanggal' => 'date',
    ];

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class, 'guru_mapel_id', 'id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id', 'id');
    }

    // --- PERUBAHAN DI SINI ---
    // Nama fungsi dan model yang dirujuk diubah ke Semester
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id');
    }

    public function jamMasukDetail(): BelongsTo
    {
        return $this->belongsTo(JamSekolah::class, 'jam_masuk', 'id');
    }

    public function jamKeluarDetail(): BelongsTo
    {
        return $this->belongsTo(JamSekolah::class, 'jam_keluar', 'id');
    }

    public function getBySiswaDetil(): HasMany
    {
        return $this->hasMany(PresensiSiswaDetail::class, 'presensi_guru_mapel_id', 'id');
    }
}