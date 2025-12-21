<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamSekolah extends Model
{
    use HasFactory;

    protected $table = 'jam_sekolah';

    protected $fillable = [
        'tahun_ajaran_id',
        'semester',
        'keterangan',
        'file_path',
    ];

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id');
    }
}