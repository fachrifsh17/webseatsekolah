<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalProduktif extends Model
{
    use HasFactory;

    protected $table = 'jadwal_produktif';

    protected $fillable = [
        'jurusan_id',
        'tahun_ajaran_id',   // ganti guru_staf_id dengan tahun_ajaran_id
        'judul',
        'penjelasan_jadwal',
        'file_jadwal_path',
    ];

    protected $casts = [
        'jurusan_id' => 'string',
        'tahun_ajaran_id' => 'string',  // sesuaikan tipe cast
    ];

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'id');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'id');
    }
}
