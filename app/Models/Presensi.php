<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presensi extends Model
{
    use HasFactory;

    protected $table = 'presensi';

    protected $fillable = [
        'siswa_id',
        'guru_staf_id',
        'kelas_id', // TAMBAHKAN INI
        'tahun_ajaran_id',
        'tanggal',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'siswa_id' => 'string',
        'guru_staf_id' => 'string',
        'kelas_id' => 'string', // TAMBAHKAN INI (karena VARCHAR 10)
        'tahun_ajaran_id' => 'string',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'id');
    }

    public function guruStaf(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id', 'id');
    }

    // TAMBAHKAN RELASI KE KELAS
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'id');
    }
}