<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KalenderAkademik extends Model
{
    use HasFactory;

    protected $table = 'kalender_akademik';

    protected $fillable = [
        'kegiatan',
        'tanggal_mulai',
        'tanggal_selesai',
        'kategori',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'datetime:Y-m-d',
        'tanggal_selesai' => 'datetime:Y-m-d',
    ];
}
