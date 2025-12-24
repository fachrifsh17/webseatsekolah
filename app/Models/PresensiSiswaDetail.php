<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiSiswaDetail extends Model
{
    protected $table = 'presensi_siswa_detail';

    protected $fillable = [
        'presensi_guru_mapel_id',
        'siswa_id',
        'status',
        'catatan',
    ];

    public function presensiGuruMapel(): BelongsTo
    {
        return $this->belongsTo(PresensiGuruMapel::class, 'presensi_guru_mapel_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}