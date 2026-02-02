<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
=======
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
>>>>>>> master

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

<<<<<<< HEAD
    public function presensiSiswaDetail(): HasMany
    {
        return $this->hasMany(PresensiSiswaDetail::class, 'presensi_guru_mapel_id');
    }

    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class, 'guru_mapel_id');
=======
    public function guruMapel(): BelongsTo
    {
        return $this->belongsTo(GuruMapel::class, 'guru_mapel_id', 'id');
>>>>>>> master
    }

    public function kelas(): BelongsTo
    {
<<<<<<< HEAD
        return $this->belongsTo(Kelas::class, 'kelas_id');
=======
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
>>>>>>> master
    }

    public function mataPelajaran(): BelongsTo
    {
<<<<<<< HEAD
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
=======
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
>>>>>>> master
    }
}