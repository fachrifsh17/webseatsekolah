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
        'guru_staf_id',
        'judul',
        'penjelasan_jadwal',
        'file_jadwal_path',
    ];

    protected $casts = [
        'jurusan_id' => 'string',
        'guru_staf_id' => 'string',
    ];

    public function jurusan(): BelongsTo
    {
        return $this->belongsTo(Jurusan::class, 'jurusan_id', 'id');
    }

    public function guruStaf(): BelongsTo
    {
        return $this->belongsTo(GuruStaf::class, 'guru_staf_id', 'id');
    }
}