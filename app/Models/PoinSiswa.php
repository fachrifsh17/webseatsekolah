<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoinSiswa extends Model
{
    use HasFactory;

    protected $table = 'poin_siswa';

    protected $fillable = [
        'siswa_id',
        'guru_staf_id',
        'tahun_ajaran_id',
        'indikator',
        'poin_positif',
        'poin_negatif',
        'tanggal',
        'keterangan',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function guruStaf(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }
}