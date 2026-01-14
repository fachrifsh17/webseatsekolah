<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function presensiSiswaDetail(): HasMany
    {
        return $this->hasMany(PresensiSiswaDetail::class, 'presensi_guru_mapel_id');
    }

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class, 'guru_mapel_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }
}