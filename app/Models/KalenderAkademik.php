<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderAkademik extends Model
{
    use HasFactory;

    protected $table = 'kalender_akademik';

    protected $fillable = [
        'tahun_ajaran_id', 
        'kegiatan',
        'tanggal_mulai',
        'tanggal_selesai',
        'kategori',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'datetime:Y-m-d',
        'tanggal_selesai' => 'datetime:Y-m-d',
    ];

   
    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'id');
    }
}