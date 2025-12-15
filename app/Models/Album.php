<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    use HasFactory;

    // sesuaikan jika tabelmu bernama 'albums'
    protected $table = 'album';

    protected $fillable = [
        'nama_album',
        'tanggal_kegiatan',
        'cover_path',
    ];

    // aktifkan jika migration punya created_at/updated_at
    public $timestamps = false;

    protected $casts = [
        'tanggal_kegiatan' => 'date:Y-m-d',
    ];
}
