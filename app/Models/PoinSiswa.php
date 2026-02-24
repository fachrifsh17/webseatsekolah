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
        'kelas_id', // Tambahkan ini
        'tahun_ajaran_id',
        'indikator',
        'poin_positif',
        'poin_negatif',
        'tanggal',
        'keterangan',
    ];

    protected $appends = ['total_poin'];

    protected $casts = [
        'tanggal'      => 'date',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'poin_positif' => 'integer',
        'poin_negatif' => 'integer',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function guruStaf(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id');
    }

    public function guru(): BelongsTo
    {
        return $this->guruStaf();
    }

    // Tambahkan relasi ke Kelas
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }

    public function getTotalPoinAttribute(): int
    {
        return ($this->poin_positif ?? 0) - ($this->poin_negatif ?? 0);
    }
}