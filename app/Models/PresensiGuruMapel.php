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
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'materi',
    ];

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class, 'guru_mapel_id', 'id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id', 'id');
    }

    public function jamMasukDetail(): BelongsTo
    {
        return $this->belongsTo(JamSekolah::class, 'jam_masuk', 'id');
    }

    public function jamKeluarDetail(): BelongsTo
    {
        return $this->belongsTo(JamSekolah::class, 'jam_keluar', 'id');
    }

    public function presensiSiswaDetail(): HasMany
    {
        return $this->hasMany(PresensiSiswaDetail::class, 'presensi_guru_mapel_id', 'id');
    }
}