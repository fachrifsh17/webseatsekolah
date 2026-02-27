<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresensiDetail extends Model
{
    use HasFactory;

    protected $table = 'presensi_detail';

    protected $fillable = [
        'presensi_id',
        'siswa_id',
        'status',
        'keterangan',
    ];

    protected $casts = [
        'presensi_id' => 'integer',
        'siswa_id' => 'string',
    ];

    public function presensi(): BelongsTo
    {
        return $this->belongsTo(Presensi::class, 'presensi_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id', 'id');
    }
}